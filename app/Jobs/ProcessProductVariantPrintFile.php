<?php

namespace App\Jobs;

use App\Logger\ConnectorLoggerFactory;
use App\Models\Blanks\BlankPrintImage;
use App\Models\FileModel;
use App\Models\ImageCanvas;
use App\Models\ImageCanvasSubArtwork;
use App\Models\Products\ProductPrintFile;
use App\Models\Products\ProductVariant;
use App\Models\Products\ProductVariantPrintFile;
use App\Models\Products\ProductVariantStageFile;
use Carbon\Carbon;
use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Http\File;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Imagick;


use Intervention\Image\Image;
use Intervention\Image\ImageManager;


class ProcessProductVariantPrintFile extends BaseJob
{
    protected $accountId;

    /**
     * @var int
     */
    public $tries = 15;

    public $retryAfter = 60;

    /**
     * @var int
     */
    public $timeout = 1200;

    /**
     * @var ProductVariantPrintFile
     */
    protected $productVariantPrintFile;

    /**
     * @var string
     */
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
     * @var ProductVariant
     */
    protected $productVariant;

    /**
     * @var string
     */
    protected $fileExtension;

    protected $blankStageLocation;

    /**
     * Create a new job instance.
     *
     * @param ProductVariantPrintFile $productVariantPrintFile
     */
    public function __construct(ProductVariantPrintFile $productVariantPrintFile)
    {
        parent::__construct();
        $this->productVariantPrintFile = $productVariantPrintFile;
        $this->accountId = $this->productVariantPrintFile->account_id;
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
            $this->productVariantPrintFile->account_id,
            'system',
            $this->processId);
        $this->logger = $loggerFactory->getLogger();

        $this->logger->info("-------------------- Start ProcessProductVariantPrintFile | ID: {$this->productVariantPrintFile->id} | Attempt: {$this->attempts()} --------------------");
        if (!isset($this->productVariantPrintFile->product) || !isset($this->productVariantPrintFile->variant) || $this->productVariantPrintFile->product->deleted_at || $this->productVariantPrintFile->variant->deleted_at) {
            $this->logger->info("Product Variant has been deleted, abort process");
            return;
        }

        if ($this->productVariantPrintFile->status === ProductVariantStageFile::STATUS_FINISHED) {
            $this->logger->info("Already finished");
            return;
        }

        if ($this->productVariantPrintFile->status !== ProductVariantStageFile::STATUS_PENDING) {
            $this->logger->info("Another process is handling this file");
            return;
        }

        //Check that all Stage Files are created
        $unfinishedStageFiles = ProductVariantStageFile::where([['product_id', $this->productVariantPrintFile->product_id],['status', '!=', ProductVariantStageFile::STATUS_FINISHED]])->count();
        if ($unfinishedStageFiles > 0) {
            $this->logger->info("Found $unfinishedStageFiles unfinished Stage Files");
            if ($this->attempts() < $this->tries - 2) {
                $releaseTime = $this->retryAfter;
                $this->logger->info("Retry Job after {$releaseTime}s");
                $this->release($releaseTime);
                return;
            } else {
                $this->logger->info("Waited {$this->attempts()} attempts and Stage Files still processing, continuing with Print File process");
            }
        }

        //Grab matching print files
        $matchingWhere = [
            'account_id' => $this->productVariantPrintFile->account_id,
            'product_id' => $this->productVariantPrintFile->product_id,
            'blank_print_image_id' => $this->productVariantPrintFile->blank_print_image_id,
            'blank_stage_id' => $this->productVariantPrintFile->blank_stage_id,
            'status' => ProductVariantPrintFile::STATUS_PENDING
        ];

        $batchMatching = true;
        $printFilesToProcess = [];
        if ($batchMatching) {
            //Update mockups that will have matching images
            $printFilesToProcess = ProductVariantPrintFile::where($matchingWhere)->get();
            if ($printFilesToProcess) {
                $this->logger->debug("Print Files to Handle: " . json_encode($printFilesToProcess->pluck('id')) . " | Count: " . count($printFilesToProcess));
                ProductVariantPrintFile::whereIn('id', $printFilesToProcess->pluck('id'))->update([
                    'started_at' => Carbon::now(),
                    'status' => ProductVariantPrintFile::STATUS_STARTED,
                ]);
            }
        } else {
            $this->productVariantPrintFile->started_at = Carbon::now();
            $this->productVariantPrintFile->status = ProductVariantPrintFile::STATUS_STARTED;
            $this->productVariantPrintFile->save();
        }

        $productPrintFile = ProductPrintFile::firstOrCreate([
            'account_id' => $this->productVariantPrintFile->account_id,
            'product_id' => $this->productVariantPrintFile->product_id,
            'blank_print_image_id' => $this->productVariantPrintFile->blank_id,
            'blank_stage_id' => $this->productVariantPrintFile->blank_option_value_id
        ]);

        //$this->logger->debug("productVariantPrintFile: " . json_encode($this->productVariantPrintFile));

        $this->productVariant = $this->productVariantPrintFile->variant;
        $blankVariant = $this->productVariant->blankVariant;
        $blank = $blankVariant->blank;
        $category = $blank->category;
        $this->isApparel = strtolower($category->name) == 'apparel';

        //$this->logger->debug("productVariant: " . json_encode($this->productVariant));

        $printImage = BlankPrintImage::where('id', $this->productVariantPrintFile->blank_print_image_id)->with(['imageCanvases' => function ($query) {
            $query->where('blank_stage_group_id', $this->productVariantPrintFile->blankStage->blankStageGroup->id)->with(['imageCanvasSubArtwork' => function ($query) {
                $query->where('blank_Stage_id', $this->productVariantPrintFile->blank_stage_id);
            }]);
        }])->with('imageCanvases.imageType')->first();

        $this->logger->debug('printImage:' . json_encode($printImage));

        $this->imageManager = new ImageManager(array('driver' => 'imagick'));

        foreach ($printImage->imageCanvases as $imageCanvas) {
            //$this->logger->debug("imageCanvas: " . json_encode($imageCanvas));

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

        if ($batchMatching) {
            //Update matching
            if ($printFilesToProcess) {
                $this->productVariantPrintFile->refresh();
                $this->logger->debug("Print Files to Update: " . json_encode($printFilesToProcess->pluck('id')));

                foreach ($printFilesToProcess as $printFileToProcess) {
                    if ($printFileToProcess->id !== $this->productVariantPrintFile->id) {
                        try {
                            $printFileToProcess->file_name = $this->productVariantPrintFile->file_name;
                            $this->logger->debug("Copy file from {$this->productVariantPrintFile->file_path} to {$printFileToProcess->file_path}");
//                            if (Storage::copy($this->productVariantPrintFile->file_path, $printFileToProcess->file_path)) {
//                                $printFileToProcess->status = ProductVariantStageFile::STATUS_FINISHED;
//                                $printFileToProcess->finished_at = Carbon::now();
//                                $printFileToProcess->save();
//                            }

                            if ($printFileToProcess->copyFile($this->productVariantPrintFile->file_path, $printFileToProcess->file_name)) {
                                $printFileToProcess->status = ProductVariantPrintFile::STATUS_FINISHED;
                                $printFileToProcess->finished_at = Carbon::now();
                                $printFileToProcess->save();
                            }

                        } catch (Exception $e) {
                            $this->logger->error($e);
                        }
                    }
                }

            }
        } else {
            $this->productVariantPrintFile->status = ProductVariantPrintFile::STATUS_FINISHED;
            $this->productVariantPrintFile->finished_at = \Illuminate\Support\Carbon::now();
            $this->productVariantPrintFile->save();
        }

        $this->logger->info("-------------------- End ProcessProductVariantPrintFile | ID: {$this->productVariantPrintFile->id} --------------------");

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

        $canvasWidth = (int)$imageCanvas->width ?? (int)$this->productVariantPrintFile->width;
        $canvasHeight = (int)$imageCanvas->height ?? (int)$this->productVariantPrintFile->height;

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
            $this->layerImageOnCanvas($imageCanvas, $imageCanvasSubArtwork);
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
     * @param ImageCanvas $imageCanvas
     * @param ImageCanvasSubArtwork $imageCanvasSubArtwork
     * @throws Exception
     */
    protected function layerImageOnCanvas($imageCanvas, $imageCanvasSubArtwork)
    {
        $this->logger->info("----- Process ImageCanvasSubArtwork | ID: {$imageCanvasSubArtwork->id} -----");
        $this->logger->debug('imageCanvasSubArtwork:' . json_encode($imageCanvasSubArtwork));

        $stageFileQuery = ProductVariantStageFile::where([['product_id', $this->productVariantPrintFile->product_id], ['product_variant_id', $this->productVariantPrintFile->product_variant_id], ['blank_stage_group_id', $imageCanvas->blank_stage_group_id], ['blank_stage_id', $imageCanvasSubArtwork->blank_stage_id]])->with('blankStageLocation');

        $this->logger->debug('stageFileQuery:' . $stageFileQuery->toSql() . " | " . json_encode($stageFileQuery->getBindings()));

        $stageFile = $stageFileQuery->first();
        $this->logger->debug('$stageFile:' . json_encode($stageFile));

        $this->logger->debug('Queried Stage File:' . ($stageFile->id ?? ''));
        $this->logger->debug('Original Stage File:' . ($this->productVariantPrintFile->stageFile->id ?? ''));

        $this->blankStageLocation = $stageFile->blankStageLocation;

        //TODO: May not be needed now that we're checking use_original_artwork
        if (!$stageFile) {
            $this->logger->error('No stage file found with complex query, using stage file id from ProductVariantPrintFile');
            $stageFile = $this->productVariantPrintFile->stageFile;
            if (!$stageFile) {
                throw new Exception('Stage File is missing');
            }
        }

        $artFile = $stageFile->productArtFile;

        $this->logger->debug("Layer file $artFile->file_url_original");

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
        $this->logger->info("----- Save to S3 -----");

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

//            try {
//                $this->logger->info("Create Temp File using Storage: $tmpFilePath");
//                $response = Storage::disk('tmp')->put($tmpFilePath, $imagickObject);
//                $this->logger->info("Response: $response");
//            } catch (Exception $e) {
//                $this->logger->error($e);
//            }

            $tmpFilePath = $tmpFileDir . DIRECTORY_SEPARATOR . Str::uuid() . '.pdf';
            try {
                $this->logger->info("Create Imagick Temp File At: $tmpFilePath");
                $imagickObject->writeImage($tmpFilePath);
            } catch (Exception $e) {
                $this->logger->error($e);
            }

            $success = $this->productVariantPrintFile->saveFile(new File($tmpFilePath), $fileName, $this->productVariantPrintFile->file_dir, $isPublic = true);

            //unset($tmpFilePath);
        } else {
            $success = $this->productVariantPrintFile->saveFile($this->finalImage->stream($this->fileExtension, 90)->__toString(), $fileName, $this->productVariantPrintFile->file_dir, $isPublic = true);
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
        //Convert to greyscale if is_silhouette
        if ($this->productVariant->blankVariant->blank->is_silhouette_artwork) {
            $this->logger->debug("Create Art Silhouette");
            $this->finalImage->greyscale()->limitColors(1)->contrast(100)->invert();
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

        $fileNameArray[] = $this->productVariant->product['name'];
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
        $this->logger->debug("Use original file");

        $stageFile = $this->productVariantPrintFile->stageFile;

        $this->logger->debug("stageFile: " . json_encode($stageFile));

        $artFile = $stageFile->productArtFile;

        $this->finalImage = $this->imageManager->make($artFile->file_url_original);
        $this->finalImage = $this->fixColorSpace($this->finalImage);
        $this->fileExtension = $artFile->imageType->file_extension;
        $this->blankStageLocation = $stageFile->blankStageLocation;
    }
}
