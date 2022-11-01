<?php

namespace App\Jobs;

use App\Logger\ConnectorLoggerFactory;
use App\Models\Blanks\BlankPrintImage;
use App\Models\Blanks\BlankPsd;
use App\Models\FileModel;
use App\Models\ImageCanvas;
use App\Models\ImageCanvasSubArtwork;
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
use Intervention\Image\Image;
use App\Helpers\ImageHelper;

class ProcessProductPrintFile extends BaseJob
{
    protected $accountId;

    /**
     * @var ProductPrintFile
     */
    protected $productPrintFile;

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
     * @param ProductPrintFile $productPrintFile
     */
    public function __construct(ProductPrintFile $productPrintFile)
    {
        parent::__construct();
        $this->productPrintFile = $productPrintFile;
        $this->accountId = $this->productPrintFile->account_id;
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
            $this->productPrintFile->account_id,
            'system',
            $this->processId);
        $this->logger = $loggerFactory->getLogger();

        $this->logger->title("Start ProcessProductPrintFile | ID: {$this->productPrintFile->id} | Product ID: {$this->productPrintFile->product_id} |  Attempt: {$this->attempts()}/{$this->tries}");

        if (!isset($this->productPrintFile->product) || $this->productPrintFile->product->deleted_at) {
            $this->logger->info("Product has been deleted, abort process");
            return;
        }

        $this->productPrintFile->status = ProductPrintFile::STATUS_STARTED;
        $this->productPrintFile->started_at = Carbon::now();
        $this->productPrintFile->save();

        $this->createPrintFile();

        //Dispatch ProductVariantPrintFiles
        foreach ($this->productPrintFile->productVariantPrintFiles as $productVariantPrintFile) {
            $this->logger->info("Dispatching ProductVariantPrintFiles ID $productVariantPrintFile->id");
            //ProcessProductVariantPrintFiles::dispatch($productVariantPrintFile);
        }

        $this->logger->title("End ProcessProductPrintFile | ID: {$this->productPrintFile->id} | Product ID: {$this->productPrintFile->product_id} |  Attempt: {$this->attempts()}/{$this->tries}");
    }

    /**
     * @throws Exception
     */
    public function createPrintFile()
    {
        $this->logger->header("Create Print File");

        //$this->productVariant = $this->productVariantPrintFile->variant;

        //$blankVariant = $this->productVariant->blankVariant;
        $blank = $this->productPrintFile->blank;
        $category = $blank->category;
        $this->isApparel = strtolower($category->name) == 'apparel';

        $printImage = BlankPrintImage::where('id', $this->productPrintFile->blank_print_image_id)->with(['imageCanvases' => function ($query) {
            $query->where('blank_stage_group_id', $this->productPrintFile->blankStage->blankStageGroup->id)->with(['imageCanvasSubArtwork' => function ($query) {
                $query->where('blank_Stage_id', $this->productPrintFile->blank_stage_id);
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

            if ($this->productPrintFile->blank->is_silhouette_artwork) {
                $this->convertToSilhouette();
            }

            $this->saveFile();
        }
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
     * @param $imageCanvas
     * @throws Exception
     */
    protected function buildCanvasWithLayers($imageCanvas)
    {
        $this->fileExtension = $imageCanvas->imageType->file_extension;

        $canvasWidth = (int)$imageCanvas->width ?? (int)$this->productPrintFile->width;
        $canvasMaxWidth = (int)$imageCanvas->max_width ?? 0;
        $canvasHeight = (int)$imageCanvas->height ?? (int)$this->productPrintFile->height;
        $canvasMaxHeight = (int)$imageCanvas->max_height ?? 0;

        if ($canvasWidth > 0 && $canvasWidth > (int)$this->productPrintFile->width) {
            $this->logger->info("Create Canvas Width $canvasWidth");
        }
        elseif($canvasMaxWidth > 0 && (int)$this->productPrintFile->width < $canvasMaxWidth && (int)$this->productPrintFile->width > $canvasWidth){
                $this->logger->info("Create Canvas Image Based Width $imageCanvas->max_width");
                $canvasWidth = (int)$this->productPrintFile->width;
        }
        elseif($canvasMaxWidth > 0 && (int)$this->productPrintFile->width > $canvasMaxWidth && (int)$this->productPrintFile->width > $canvasWidth) {
            $this->logger->info("Create Canvas Max Width $imageCanvas->max_width");
             $canvasWidth = $canvasMaxWidth;
        }

        if ($canvasHeight > 0 && $canvasHeight > (int)$this->productPrintFile->height) {
            $this->logger->info("Create Canvas Height $canvasHeight");
        }
        elseif($canvasMaxHeight > 0 && (int)$this->productPrintFile->height < $canvasMaxHeight && (int)$this->productPrintFile->height > $canvasHeight) {
            $this->logger->info("Create Canvas Image Based Height $imageCanvas->max_height");
            $canvasHeight = (int)$this->productPrintFile->height;
        }
        elseif($canvasMaxHeight > 0 && (int)$this->productPrintFile->height > $canvasMaxHeight && (int)$this->productPrintFile->height > $canvasHeight) {
            $this->logger->info("Create Canvas Max Height $imageCanvas->max_height");
            $canvasHeight = $canvasMaxHeight;
        }

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
        $this->finalImage = ImageHelper::processImageCanvasSubArtwork(
            $this->finalImage,
            $imageCanvas,
            $this->productPrintFile,
            $this->logger
        );

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

        $this->logger->info("Saving file as: $fileName | Extension: {$this->fileExtension}");

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

            $success = $this->productPrintFile->saveFile(new File($tmpFilePath), $fileName, $this->productPrintFile->file_dir, $isPublic = true);

            //unset($tmpFilePath);
        } else {
            $success = $this->productPrintFile->saveFile($this->finalImage->stream($this->fileExtension, 100)->__toString(), $fileName, $this->productPrintFile->file_dir, $isPublic = true);
        }

        if ($success) {
            $this->logger->info("Success");
            return true;

        } else {
            $this->logger->info("Failed to save");
        }

        return false;
    }

    protected function convertToSilhouette()
    {
        $this->logger->debug("Create Art Silhouette");
        //$this->finalImage->greyscale()->limitColors(1)->contrast(100)->invert(); //Good on color
        //$this->finalImage->limitColors(1)->greyscale()->contrast(100)->invert();
        //$this->finalImage->limitColors(1)->greyscale()->contrast(100); //Good on B&W
        // $this->finalImage->limitColors(1)->greyscale()->invert(); //Not good on B&W
        // $this->finalImage->limitColors(1)->greyscale(); //Good on B&W | Light grey Color
        //$this->finalImage->limitColors(1)->greyscale()->brightness(-99); //Jaggy edges on B&W

        // $this->finalImage->greyscale();

        // taken from v1 app for processing tumblers and adjusted for our needs
        $art = $this->finalImage->getCore();
        $artWidth = $art->getImageWidth();
        $artHeight = $art->getImageHeight();

        $imBlackOnTrans = new Imagick();
        $imBlackOnTrans->setResolution(300,300);
        $imBlackOnTrans->newImage($artWidth, $artHeight, "#000000");
        $imBlackOnTrans->setImageMatte(1);
        $imBlackOnTrans->compositeImage($art, Imagick::COMPOSITE_DSTIN, 0, 0);
        $art->clear();

        $imBlackOnWhite = new Imagick();
        $imBlackOnWhite->setResolution(300 ,300 );
        $imBlackOnWhite->newImage($artWidth, $artHeight, "#FFFFFF");
        $imBlackOnWhite->compositeImage($imBlackOnTrans, Imagick::COMPOSITE_OVER, 0, 0);

        $imBlackOnTrans->stripimage();
        $imBlackOnWhite->stripimage();
        $imBlackOnTrans->clear();

        $this->finalImage = $this->imageManager->make($imBlackOnWhite);
    }

    protected function getFileName()
    {
        $blankVariantOptionValues = [];
//        foreach ($this->productVariant->blankVariant['optionValues'] as $blankVariantOptionValue) {
//            if (stripos($blankVariantOptionValue->option->name, 'size') === false) {
//                $blankVariantOptionValues[] = str_replace(' ', '_', $blankVariantOptionValue['name']);
//            }
//        }

        $fileNameArray[] = $this->productPrintFile->product->name;
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

        $artFile = $this->productPrintFile->productArtFile;

        $this->fileExtension = $artFile->imageType->file_extension;
        $this->blankStageLocation = $this->productPrintFile->blankStageLocation;

        $this->finalImage = $this->imageManager->make($artFile->file_url_original);
        $this->finalImage = $this->fixColorSpace($this->finalImage);
    }
}
