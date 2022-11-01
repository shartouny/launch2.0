<?php

namespace App\Jobs;

use App\Logger\ConnectorLoggerFactory;
use App\Models\Blanks\BlankPrintImage;
use App\Models\Blanks\BlankPsd;
use App\Models\FileModel;
use App\Models\ImageCanvas;
use App\Models\ImageCanvasSubArtwork;
use App\Models\Orders\OrderLineItemArtFile;
use App\Models\Orders\OrderLineItemPrintFile;
use App\Models\Orders\OrderStatus;
use App\Models\Products\ProductArtFile;
use App\Models\Products\ProductPrintFile;

use App\Models\Products\ProductVariant;
use App\Models\Products\ProductVariantMockupFile;
use App\Models\Products\ProductVariantStageFile;
use Exception;
use Illuminate\Http\File;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Imagick;
use Intervention\Image\ImageManager;

class ProcessOrderLineItemPrintFile extends BaseJob
{
    protected $accountId;

    /**
     * @var ProductPrintFile
     */
    protected $orderLineItemPrintFile;

    public $logChannel = 'print-files';

    /**
     * @var Intervention\Image\ImageManager
     */
    protected $imageManager;

    /**
     * @var bool
     */
    protected $isApparel = false;

    /**
     * @var $finalImage
     */
    protected $finalImage;

    /**
     * @var string
     */
    protected $fileExtension;

    protected $blankStageLocation;

    /**
     * Create a new job instance.
     *
     * @param OrderLineItemPrintFile $orderLineItemPrintFile
     */
    public function __construct(OrderLineItemPrintFile $orderLineItemPrintFile)
    {
        parent::__construct();
        $this->orderLineItemPrintFile = $orderLineItemPrintFile;
        $this->accountId = $this->orderLineItemPrintFile->account_id;
    }

    /**
     * Execute the job.
     *
     * @return void
     * @throws Exception
     */
    public function handle()
    {
        $loggerFactory = new ConnectorLoggerFactory(
            $this->logChannel,
            $this->orderLineItemPrintFile->account_id,
            'system',
            $this->processId);
        $this->logger = $loggerFactory->getLogger();

        $this->logger->title("Start ProcessOrderLineItemPrintFile | ID: {$this->orderLineItemPrintFile->id} | Product ID: {$this->orderLineItemPrintFile->product_id} |  Attempt: {$this->attempts()}/{$this->tries}");

        if ($this->orderLineItemPrintFile->deleted_at) {
            $this->logger->info("OrderLineItemPrintFile has been deleted, abort process");
            return;
        }

        if (!isset($this->orderLineItemPrintFile->product) || $this->orderLineItemPrintFile->product->deleted_at) {
            $this->logger->info("Product has been deleted, abort process");
            return;
        }

        if($this->orderLineItemPrintFile->status !== ProductPrintFile::STATUS_PENDING){
            $status = OrderStatus::getStatusName($this->orderLineItemPrintFile->status);
            $this->logger->info("Status is not in Pending state, abort process | Status: {$status}");
            return;
        }

        $this->orderLineItemPrintFile->status = ProductPrintFile::STATUS_STARTED;
        $this->orderLineItemPrintFile->started_at = Carbon::now();
        $this->orderLineItemPrintFile->save();

        $this->createPrintFile();

        $this->orderLineItemPrintFile->status = ProductPrintFile::STATUS_FINISHED;
        $this->orderLineItemPrintFile->finished_at = Carbon::now();
        $this->orderLineItemPrintFile->save();

        $this->logger->title("End ProcessOrderLineItemPrintFile | ID: {$this->orderLineItemPrintFile->id} | Product ID: {$this->orderLineItemPrintFile->product_id} |  Attempt: {$this->attempts()}/{$this->tries}");
    }

    function fixColorSpace($img)
    {
        $imagickObject = $img->getCore();
        if ($imagickObject->getImageColorspace() == \Imagick::COLORSPACE_CMYK) {
            $imagickObject->transformimagecolorspace(\Imagick::COLORSPACE_SRGB);
            return $this->imageManager->make($imagickObject);
        } else {
            return $img;
        }
    }

    /**
     * @throws Exception
     */
    public function createPrintFile()
    {
        $this->logger->header("Create Print File");

        $blank = $this->orderLineItemPrintFile->blank;
        $category = $blank->category;
        $this->isApparel = strtolower($category->name) == 'apparel';

        $printImage = BlankPrintImage::where('id', $this->orderLineItemPrintFile->blank_print_image_id)->with(['imageCanvases' => function ($query) {
            $query->where('blank_stage_group_id', $this->orderLineItemPrintFile->blankStage->blankStageGroup->id)->with(['imageCanvasSubArtwork' => function ($query) {
                $query->where('blank_Stage_id', $this->orderLineItemPrintFile->blank_stage_id);
            }]);
        }])->with('imageCanvases.imageType')->first();

        $this->logger->debug('Print Image:' . json_encode($printImage));

        $this->imageManager = new ImageManager(array('driver' => 'imagick'));

        foreach ($printImage->imageCanvases as $imageCanvas) {
            $this->logger->subheader("Image Canvas ID $imageCanvas->id");

            if ($printImage->use_original_artwork) {
                $this->buildOriginalFile();
            } else {
                if (count($imageCanvas->imageCanvasSubArtwork) === 0) {
                    throw new Exception('Print Image Canvas has no layers');
                }
                $this->buildCanvasWithLayers($imageCanvas);
            }

            $this->convertToGreyscale();

            $this->saveFile();
        }
    }


    /**
     * @param Image $image
     * @param $imageCanvasSubArtwork
     * @return mixed
     */
    protected function resizeImage($image, $imageCanvasSubArtwork, $keepAspect = true)
    {
        //If width and height is 0 dont resize
        if ($imageCanvasSubArtwork->width > 0 && $imageCanvasSubArtwork->height > 0) {
            $this->logger->info("Resize image to $imageCanvasSubArtwork->width x $imageCanvasSubArtwork->height");
            if($keepAspect){
                $image->resize($imageCanvasSubArtwork->width, $imageCanvasSubArtwork->height, function ($constraint) {
                    $constraint->aspectRatio();
                    $constraint->upsize();
                });
            } else {
                $image->resize($imageCanvasSubArtwork->width, $imageCanvasSubArtwork->height, function ($constraint) {
                });
            }
        }
        $this->logger->info("No Resize necessary");
        return $image;
    }

    /**
     * @param $imageCanvas
     * @throws Exception
     */
    protected function buildCanvasWithLayers($imageCanvas)
    {
        $this->fileExtension = $imageCanvas->imageType->file_extension;

        $canvasWidth = (int)$imageCanvas->width ?? (int)$this->orderLineItemPrintFile->width;
        $canvasHeight = (int)$imageCanvas->height ?? (int)$this->orderLineItemPrintFile->height;

        $this->logger->info("Create canvas $canvasWidth x $canvasHeight");
        if (empty($imageCanvas->background_hex)) {
            $this->logger->debug("No background color set for canvas");
            $this->finalImage = $this->imageManager->canvas($canvasWidth, $canvasHeight);
        } else {
            $this->logger->debug("Set canvas background color to $imageCanvas->background_hex");
            $this->finalImage = $this->imageManager->canvas($canvasWidth, $canvasHeight, $imageCanvas->background_hex);
        }

        $this->logger->info("----- Found " . count($imageCanvas->imageCanvasSubArtwork) . " ImageCanvasSubArtworks -----");

        //Create canvas and layer sub artwork on top with size and positioning data
        foreach ($imageCanvas->imageCanvasSubArtwork as $imageCanvasSubArtwork) {
            $this->layerImageOnCanvas($imageCanvasSubArtwork);
        }

        //Check overlay_blank_image_id on imageCanvas
        if ($imageCanvas->blankImageOverlay) {
            $this->layerOverlayOnCanvas($imageCanvas->blankImageOverlay);
        }

        //Get Imagick Core
        $imagickObject = $this->finalImage->getCore();

        //Set DPI through Imagick
        if ($imageCanvas->dpi > 0) {
            $imagickObject->setImageUnits(Imagick::RESOLUTION_PIXELSPERINCH);
            $imagickObject->setImageResolution($imageCanvas->dpi, $imageCanvas->dpi);
        }

        //Convert color profile to srgb
        //inspired by https://github.com/mosbth/cimage/blob/master/CImage.php#L2552
        $colorspace = $imagickObject->getImageColorspace();
        $profiles = $imagickObject->getImageProfiles('*', false);
        $hasICCProfile = (array_search('icc', $profiles) !== false);
        if ($colorspace != Imagick::COLORSPACE_SRGB || $hasICCProfile) {
            $sRGBicc = file_get_contents(base_path('resources/icc/sRGB_IEC61966-2-1_black_scaled.icc'));
            $imagickObject->profileImage('icc', $sRGBicc);
            $imagickObject->transformImageColorspace(Imagick::COLORSPACE_SRGB);
        }

        $this->finalImage = $this->imageManager->make($imagickObject);
    }

    /**
     * @param ImageCanvasSubArtwork $imageCanvasSubArtwork
     * @throws Exception
     */
    protected function layerImageOnCanvas($imageCanvasSubArtwork)
    {
        $this->logger->header("Process ImageCanvasSubArtwork | ID: {$imageCanvasSubArtwork->id}");

        $this->logger->debug('imageCanvasSubArtwork:' . json_encode($imageCanvasSubArtwork));

        $artFile = OrderLineItemArtFile::where([['account_id',$this->orderLineItemPrintFile->account_id],['order_line_item_id', $this->orderLineItemPrintFile->order_line_item_id],['product_id',$this->orderLineItemPrintFile->product_id],['blank_stage_id',$imageCanvasSubArtwork->blank_stage_id]])->first();
        $this->logger->debug('artFile:' . json_encode($artFile));

        if(!$artFile) {
            $this->logger->debug('No OrderLineItemArtFile found, searching for ProductArtFile');
            $artFile = ProductArtFile::where([['account_id', $this->orderLineItemPrintFile->account_id], ['product_id', $this->orderLineItemPrintFile->product_id], ['blank_stage_id', $imageCanvasSubArtwork->blank_stage_id]])->first();
            $this->logger->debug('artFile:' . json_encode($artFile));
            if(!$artFile){
              $this->logger->warning('No art file found, skipping image');
              return;
            }
        }

        $this->logger->debug("Layer file URL: $artFile->file_url_original");

        $image = $this->imageManager->make($artFile->file_url_original);
        $image = $this->fixColorSpace($image);

        if ($this->isApparel) {
            $this->logger->debug("Trim image for apparel");
            $image->trim();
        }

        $image = $this->resizeImage($image, $imageCanvasSubArtwork, false);

        //Rotate and flip image
        if ($imageCanvasSubArtwork->is_flip_horizontal) {
            $this->logger->debug("Flip Horizontal");
            $image->flip('h');
        }

        if ($imageCanvasSubArtwork->is_flip_vertical) {
            $this->logger->debug("Flip Vertical");
            $image->flip('v');
        }

        if (!empty($imageCanvasSubArtwork->rotate_degrees)) {
            $this->logger->debug("Rotate $imageCanvasSubArtwork->rotate_degrees degrees");
            $image->rotate($imageCanvasSubArtwork->rotate_degrees);
        }

        //Check overlay_blank_image_id on imageCanvasSubArtwork
        if ($imageCanvasSubArtwork->blankImageOverlay) {
            //TODO: Paste overlay?

        }

        $this->logger->info("Insert image | Left: $imageCanvasSubArtwork->left | Top: $imageCanvasSubArtwork->top");
        $this->finalImage->insert($image, 'top-left', $imageCanvasSubArtwork->left, $imageCanvasSubArtwork->top);
    }

    /**
     * @param $blankImageOverlay
     * @throws Exception
     */
    protected function layerOverlayOnCanvas($blankImageOverlay)
    {
        $this->logger->header("Process BlankImageOverlay | ID: {$blankImageOverlay->id}");

        $this->logger->debug('BlankImageOverlay:' . json_encode($blankImageOverlay));

        $this->logger->debug("OverLay file URL: {$blankImageOverlay->file_url_original}");
        $this->logger->debug($this->finalImage->getCore()->getImageWidth());
        $this->logger->debug($this->finalImage->getCore()->getImageHeight());

        $image = $this->imageManager->make($blankImageOverlay->file_url_original);
        $image = $this->fixColorSpace($image);

        $image->resize($this->finalImage->getCore()->getImageWidth(), $this->finalImage->getCore()->getImageHeight(), function ($constraint) {
            $constraint->aspectRatio();
            $constraint->upsize();
        });

        $this->logger->info("Insert overlay image");
        $this->finalImage->insert($image);
    }

    protected function saveFile()
    {
        //Save file to S3
        $this->logger->header("Save to S3");

        $fileName = $this->getFileName();

        $this->logger->info("Saving file as: {$this->orderLineItemPrintFile->file_dir}/$fileName | Extension: {$this->fileExtension}");

        if ($this->fileExtension === 'pdf') {
            //Get Imagick Core
            $imagickObject = $this->finalImage->getCore();
            $imagickObject->setImageFormat('pdf');


            $tmpFileDir = storage_path('app/tmp');

            try {
                if (!file_exists($tmpFileDir)) {
                    $this->logger->info("Mkdir: $tmpFileDir");
                    mkdir($tmpFileDir, 0777, true);
                }
            } catch (Exception $e) {
                $this->logger->error($e);
            }

            $tmpFilePath = $tmpFileDir . DIRECTORY_SEPARATOR . Str::uuid() . '.pdf';
            try {
                $this->logger->info("Create Imagick Temp File At: $tmpFilePath");
                $imagickObject->writeImage($tmpFilePath);
            } catch (Exception $e) {
                $this->logger->error($e);
            }

            $success = $this->orderLineItemPrintFile->saveFile(new File($tmpFilePath), $fileName, $this->orderLineItemPrintFile->file_dir, $isPublic = true);

            //unset($tmpFilePath);
        } else {
            $success = $this->orderLineItemPrintFile->saveFile($this->finalImage->stream($this->fileExtension, 100)->__toString(), $fileName, $this->orderLineItemPrintFile->file_dir, $isPublic = true);
        }

        if ($success) {
            $this->logger->info("Success");
            return true;

        } else {
            $this->logger->info("Failed to save");
        }

        return false;
    }

    protected function convertToGreyscale()
    {
        //Convert to greyscale if is_silhouette_artwork
        if ($this->orderLineItemPrintFile->blank->is_silhouette_artwork) {
            $this->logger->debug("Create Art Silhouette");
            //$this->finalImage->greyscale()->limitColors(1)->contrast(100)->invert(); //Good on color
            //$this->finalImage->limitColors(1)->greyscale()->contrast(100)->invert();
            //$this->finalImage->limitColors(1)->greyscale()->contrast(100); //Good on B&W
            // $this->finalImage->limitColors(1)->greyscale()->invert(); //Not good on B&W
            // $this->finalImage->limitColors(1)->greyscale(); //Good on B&W | Light grey Color
            //$this->finalImage->limitColors(1)->greyscale()->brightness(-99); //Jaggy edges on B&W
            $this->finalImage->greyscale();

//            $this->finalImage = $this->imageManager->canvas($this->finalImage->width(), $this->finalImage->height(), '#000000')->mask($this->finalImage);
        }
    }

    protected function getFileName()
    {
        $blankVariantOptionValues = [];
//        foreach ($this->productVariant->blankVariant['optionValues'] as $blankVariantOptionValue) {
//            if (stripos($blankVariantOptionValue->option->name, 'size') === false) {
//                $blankVariantOptionValues[] = str_replace(' ', '_', $blankVariantOptionValue['name']);
//            }
//        }

        $fileNameArray[] = $this->orderLineItemPrintFile->product->name;
        if ($blankVariantOptionValues) {
            $fileNameArray[] = implode('_', $blankVariantOptionValues);
        }
        if ($this->blankStageLocation) {
            $fileNameArray[] = $this->blankStageLocation->short_name;
        }

        $fileNameArray[] = 'Print_File';
        //$fileNameArray[] = uniqid(date('YmdHis'));

        return FileModel::sanitizeFileName(implode('_', $fileNameArray) . "." . $this->fileExtension);
    }

    protected function buildOriginalFile()
    {
        $this->logger->info("Use original file");

        $artFile = $this->orderLineItemPrintFile->orderLineItemArtFile;

        $this->fileExtension = $artFile->imageType->file_extension;
        $this->blankStageLocation = $this->orderLineItemPrintFile->blankStageLocation;

        $this->finalImage = $this->imageManager->make($artFile->file_url_original);
        $this->finalImage = $this->fixColorSpace($this->finalImage);
    }
}
