<?php

namespace App\Jobs;

use App\Logger\ConnectorLoggerFactory;
use App\Models\Blanks\BlankPsd;
use App\Models\Blanks\Locations\BlankStageLocationSubSettingOffset;
use App\Models\FileModel;
use App\Models\Products\ProductMockupFile;
use App\Models\Products\ProductVariant;
use App\Models\Products\ProductVariantMockupFile;
use App\Models\Products\ProductVariantStageFile;
use Error;
use Exception;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Imagick;
use Intervention\Image\ImageManager;

ini_set('memory_limit', '4096M');

class ProcessProductVariantMockupFile extends BaseJob
{
    protected $accountId;

    public $tries = 20;
    public $retryAfter = 30;
    public $timeout = 1200;

    protected $productVariantMockupFile;
    public $logChannel = 'mockup-files';

    /**
     * Create a new job instance.
     *
     * @param ProductVariantMockupFile $productVariantMockupFile
     */
    public function __construct(ProductVariantMockupFile $productVariantMockupFile)
    {
        parent::__construct();
        $this->productVariantMockupFile = $productVariantMockupFile;
        $this->accountId = $this->productVariantMockupFile->account_id;
        if (config('app.env') === 'local') {
            $this->retryAfter = 15;
        }
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
            $this->productVariantMockupFile->account_id,
            'system',
            $this->processId);
        $this->logger = $loggerFactory->getLogger();

        $sendFilePathToPsd = config('app.send_filepath_to_psd');
        $uploadUrl = null;

        $this->logger->title("Start ProcessProductVariantMockupFile | ID: {$this->productVariantMockupFile->id} | Product ID: {$this->productVariantMockupFile->product_id} | Attempt: {$this->attempts()}/{$this->tries}");
        $this->logger->info("Product Variant ID: " . $this->productVariantMockupFile->product_variant_id);

        if (!isset($this->productVariantMockupFile->product) || !isset($this->productVariantMockupFile->productVariant) || $this->productVariantMockupFile->product->deleted_at || $this->productVariantMockupFile->productVariant->deleted_at) {
            $this->logger->info("Product Variant has been deleted, abort process");
            return;
        }

        if ($this->productVariantMockupFile->status !== ProductVariantStageFile::STATUS_PENDING) {
            $this->logger->info("Another Mockup process is handling this file");
            return;
        }

        //Check that all Stage Files are created
        $unfinishedStageFiles = ProductVariantStageFile::where([['product_id', $this->productVariantMockupFile->product_id], ['status', '!=', ProductVariantStageFile::STATUS_FINISHED]])->count();
        if ($unfinishedStageFiles > 0) {
            $this->logger->info("Found $unfinishedStageFiles unfinished Stage Files");
            if ($this->attempts() < $this->tries - 2) {
                $releaseTime = $this->retryAfter;
                $this->logger->info("Retry Job after {$releaseTime}s");
                $this->release($releaseTime);
                return;
            } else {
                $this->logger->info("Waited {$this->attempts()} attempts and Stage Files still processing, continuing with Mockup process");
            }
        }

        $this->logger->info("Send File Path to PSD Server? " . ($sendFilePathToPsd == true ? 'true' : 'false'));


        //Grab matching colors on blank_id
        $matchingWhere = [
            'account_id' => $this->productVariantMockupFile->account_id,
            'product_id' => $this->productVariantMockupFile->product_id,
            'blank_id' => $this->productVariantMockupFile->blank_id,
            'blank_option_value_id' => $this->productVariantMockupFile->blank_option_value_id,
            'blank_psd_id' => $this->productVariantMockupFile->blank_psd_id,
            'blank_stage_id' => $this->productVariantMockupFile->blank_stage_id,
            'processed_url' => null,
            'status' => ProductVariantMockupFile::STATUS_PENDING
        ];

        $batchMatching = true;
        $mockupsToProcess = [];
        if ($batchMatching) {
            //Update mockups that will have matching images
            $mockupsToProcess = ProductVariantMockupFile::where($matchingWhere)->get();
            if ($mockupsToProcess) {
                $this->logger->debug("Mockups to Handle: " . json_encode($mockupsToProcess->pluck('id')));
                ProductVariantMockupFile::whereIn('id', $mockupsToProcess->pluck('id'))->update([
                    'started_at' => Carbon::now(),
                    'status' => ProductVariantMockupFile::STATUS_STARTED,
                ]);
            }
        } else {
            $this->productVariantMockupFile->started_at = Carbon::now();
            $this->productVariantMockupFile->status = ProductVariantMockupFile::STATUS_STARTED;
            $this->productVariantMockupFile->save();
        }

//        $blankPsds = BlankPsd::where('id', $this->productVariantMockupFile->blank_psd_id)->with(['layers' => function ($query) {
//            $query->where('blank_stage_id', $this->productVariantMockupFile->blank_stage_id);
//        }])->get();

        $blankPsds = BlankPsd::where([['id', $this->productVariantMockupFile->blank_psd_id], ['is_active', true]])->with('layers')->get();
        if (!$blankPsds) {
            $this->fail(new Exception("No active Blank PSD found"));
        }
        //$this->logger->debug('blankPsds:' . json_encode($blankPsds));


        //Check if Product Mockup File already exists or create it
        $productMockupFile = ProductMockupFile::firstOrCreate([
            'account_id' => $this->productVariantMockupFile->account_id,
            'product_id' => $this->productVariantMockupFile->product_id,
            'blank_id' => $this->productVariantMockupFile->blank_id,
            'blank_option_value_id' => $this->productVariantMockupFile->blank_option_value_id,
            'blank_psd_id' => $this->productVariantMockupFile->blank_psd_id,
            'blank_stage_id' => $this->productVariantMockupFile->blank_stage_id
        ]);


        $productVariant = $this->productVariantMockupFile->productVariant;
        $blankVariant = $productVariant->blankVariant;
        $blank = $blankVariant->blank;
        $category = $blank->category;

        if (!$productMockupFile->processed_url) {
            foreach ($blankPsds as $blankPsd) {

                $displayLayers = [];
                $artUrls = [];
                $fileExtension = 'png';

                foreach ($blankPsd->layers as $blankPsdLayer) {
                    $isDisplayLayer = empty($blankPsdLayer->blank_stage_id);

                    if ($isDisplayLayer) {
                        $this->logger->info("Add display layer: $blankPsdLayer->layer_name");
                        $displayLayers[] = $blankPsdLayer->layer_name;
                    } else {
                        //Resize stage_file
                        $stageFileQuery = ProductVariantStageFile::where([['product_id', $this->productVariantMockupFile->product_id], ['product_variant_id', $this->productVariantMockupFile->product_variant_id], ['blank_stage_group_id', $blankPsd->blank_stage_group_id], ['blank_stage_id', $blankPsdLayer->blank_stage_id]]);

                        $this->logger->debug('stageFileQuery:' . $stageFileQuery->toSql() . " | " . json_encode($stageFileQuery->getBindings()));

                        $stageFileDb = $stageFileQuery->first();
                        $this->logger->debug('$stageFileDb:' . json_encode($stageFileDb));

//                        $stageFileRelationship = $this->productVariantMockupFile->stageFile;
//                        $this->logger->debug('$stageFileRelationship:' . json_encode($stageFileRelationship));
//
//                        $stageFile = $stageFileRelationship ?? $stageFileDb;

                        $stageFile = $stageFileDb;
                        $this->logger->debug('$stageFile:' . json_encode($stageFile));

                        if (!$stageFile) {
                            $this->fail(new Exception("No stage file found"));
                        }

                        $artFile = $stageFile->productArtFile;

                        //Allow reuse of image by grouping the image
                        $mockupArtDir = "accounts/" . $this->productVariantMockupFile->account_id . "/mockup-layers/" . $this->productVariantMockupFile->product_id . "/" . $this->productVariantMockupFile->blank_stage_id;
                        $stageFileName = $artFile->file_name;
                        $stageFileName = str_replace('.jpg', '.png', $stageFileName);
                        $mockupArtPath = "$mockupArtDir/$stageFileName";

                        if (!Storage::disk('s3-nocache')->exists($mockupArtPath)) {
                            $this->checkResourceUsage($artFile->file_url_original);

//                        $fileExtension = $stageFile->imageType->file_extension ?? 'png';

                            $this->logger->debug('$artFile:' . json_encode($artFile, JSON_PRETTY_PRINT));

                            $manager = new ImageManager(array('driver' => 'imagick'));

                            $blankStageLocationSubSettings = $stageFile->blankStageLocationSubSettings;
                            $this->logger->debug('$blankStageLocationSubSettings:' . json_encode($blankStageLocationSubSettings, JSON_PRETTY_PRINT));

                            $resizeAndPosition = !empty($blankStageLocationSubSettings);

                            $isApparel = strtolower($category->name) == 'apparel';
                            $this->logger->info('Is Apparel? ' . ($isApparel ? 'true' : 'false'));

                            if ($resizeAndPosition && $isApparel) {
                                $this->logger->info('Resizing and positioning image');

                                $blankStageLocationSubSettingPreview = $blankStageLocationSubSettings->preview;
                                $this->logger->debug('$blankStageLocationSubSettingPreview:' . json_encode($blankStageLocationSubSettingPreview, JSON_PRETTY_PRINT));

                                $overlayWidth = round($blankStageLocationSubSettingPreview->width ? $blankPsdLayer->width * ($blankStageLocationSubSettingPreview->width / 100) : $blankPsdLayer->width);
                                $overlayHeight = round($blankStageLocationSubSettingPreview->height ? $blankPsdLayer->height * ($blankStageLocationSubSettingPreview->height / 100) : $blankPsdLayer->height);
                                $overlayLeft = round($blankStageLocationSubSettingPreview->left ? $blankPsdLayer->width * ($blankStageLocationSubSettingPreview->left / 100) : 0);
                                $overlayTop = round($blankStageLocationSubSettingPreview->top ? $blankPsdLayer->height * ($blankStageLocationSubSettingPreview->top / 100) : 0);

                                $this->logger->debug('$blankPsdLayer->width: ' . $blankPsdLayer->width);
                                $this->logger->debug('$blankPsdLayer->height: ' . $blankPsdLayer->height);

                                $this->logger->debug('$overlayWidth: ' . $overlayWidth);
                                $this->logger->debug('$overlayHeight: ' . $overlayHeight);
                                $this->logger->debug('$overlayLeft: ' . $overlayLeft);
                                $this->logger->debug('$overlayTop: ' . $overlayTop);

                                $image = $manager->canvas($blankPsdLayer->width, $blankPsdLayer->height)->encode($fileExtension);

                                $overlayImage = $manager->make($artFile->file_url_original);

                                $overlayImage->resize($overlayWidth, $overlayHeight, function ($constraint) {
                                    $constraint->aspectRatio();
                                    $constraint->upsize();
                                })->encode('png'); //Use stream or encode?


                                $blankStageLocationSubSettingOffset = $blankStageLocationSubSettings->offsets()->where('blank_stage_location_sub_offset_id', $stageFile->blank_stage_location_sub_offset_id)->first();
                                $this->logger->debug('$blankStageLocationSubSettingOffset:' . json_encode($blankStageLocationSubSettingOffset, JSON_PRETTY_PRINT));
                                if ($blankStageLocationSubSettingOffset) {
                                    $topOffset = ($blankStageLocationSubSettingOffset->top_offset_percent / 100) * $blankPsdLayer->height;
                                    $this->logger->debug('Add top offset:' . $topOffset);
                                    $overlayTop += $topOffset;
                                }

                                $remainingWidth = $overlayWidth - $overlayImage->getWidth();
                                $remainingHeight = $overlayHeight - $overlayImage->getHeight();

                                $this->logger->debug('$overlayImage->width: ' . $overlayImage->getWidth());
                                $this->logger->debug('$overlayImage->height: ' . $overlayImage->getHeight());

                                $offsetLeft = $overlayLeft + round($remainingWidth / 2);
                                $offsetTop = $overlayTop; //$overlayTop + round($remainingHeight / 2);

                                $this->logger->debug('$offsetLeft: ' . $offsetLeft);
                                $this->logger->debug('$offsetTop: ' . $offsetTop);

                                $image->insert($overlayImage, 'top-left', $offsetLeft, $offsetTop);

                            } else {
                                $this->logger->info('No Sub Settings found, using original image');

                                $image = $manager->make($artFile->file_url_original)->resize($blankPsdLayer->width, $blankPsdLayer->height, function ($constraint) {
                                    $constraint->aspectRatio();
                                    $constraint->upsize();
                                });
                            }


                            $finalImage = $image->getCore(); //get Imagick object
                            $finalImage->setImageUnits(Imagick::RESOLUTION_PIXELSPERINCH);
                            $finalImage->setImageResolution($blankPsdLayer->dpi, $blankPsdLayer->dpi);

                            $finalImage = $manager->make($finalImage)->stream($fileExtension, 90)->__toString();

//                        $mockupArtDir = "accounts/" . $this->productVariantMockupFile->account_id . "/mockup-layers/" . $this->productVariantMockupFile->id;
//                        $stageFileName = uniqid(date('YmdHisu')) . "-" . $stageFile->file_name;

                            //Allow reuse of image by grouping the image
//                            $mockupArtDir = "accounts/" . $this->productVariantMockupFile->account_id . "/mockup-layers/" . $this->productVariantMockupFile->product_id . "/" . $this->productVariantMockupFile->blank_stage_id;
//                            $stageFileName = $stageFile->file_name;
//                            $mockupArtPath = "$mockupArtDir/$stageFileName";

                            try {
                                $this->logger->info("Save file:  $mockupArtPath | File Encoding: $fileExtension");

                                $savedFile = Storage::put($mockupArtPath, $finalImage, ['Tagging' => 'tmp=true', 'visibility' => 'private']);

                                $this->logger->info('Cloud file created? ' . ($savedFile ? 'true' : 'false') . " | Path: $mockupArtPath");
                                if (!$savedFile) {
                                    //Fail
                                    $this->logger->error("Failed to save");
                                    $this->fail(new Exception('Failed to save mockup file'));
                                }
                            } catch (Exception $e) {
                                $this->logger->error($e);
                            }
                        } else {
                            $this->logger->info("Mockup art file already exists");
                        }

                        //TODO: This needs to take into account local or cloud storage
                        $tempUrl = Storage::temporaryUrl($mockupArtPath, Carbon::now()->addMinutes(600));

                        if (!$tempUrl) {
                            $this->logger->error("No file url exists");
                            $this->fail(new Exception('File url could not be generated'));
                        }

                        // $artUrls[] = ['layerName' => $blankPsdLayer->layer_name,'artUrl' => $tempUrl];
                        $artUrls[] = [
                            'layerName' => $blankPsdLayer->layer_name,
                            'artUrl' => $tempUrl,
                            'artFileName' => str_replace('/', '_', $mockupArtPath),
                            'offsetX' => $blankPsdLayer->offset_x,
                            'offsetY' => $blankPsdLayer->offset_y
                        ];
                    }
                }

                //Send data to mockup server
                $dataToPostAr = array('images' => array());

                $fileExtension = 'png';
                if ($sendFilePathToPsd) {
                    $productVariant = ProductVariant::with('product', 'blankVariant', 'blankVariant.optionValues')->find($this->productVariantMockupFile->product_variant_id);

                    $blankVariantOptionValues = [];
                    foreach ($productVariant->blankVariant['optionValues'] as $blankVariantOptionValue) {
                        if (stripos($blankVariantOptionValue->option->name, 'size') === false) {
                            $blankVariantOptionValues[] = str_replace(' ', '_', $blankVariantOptionValue['name']);
                        }
                    }

                    $productName = $productVariant->product['name'];
                    $fileNameArray[] = $productName;//htmlentities($productName, ENT_QUOTES);
//                    if ($blankVariantOptionValues) {
//                        $fileNameArray[] = implode('_', $blankVariantOptionValues);
//                    }
                    $fileNameArray[] = ucwords($blankPsd->name);
                    $fileNameArray[] = 'Mockup';
                    //$fileNameArray[] = uniqid(date('YmdHis'));
                    $fileName = FileModel::sanitizeFileName(str_replace(' ', '_', implode('_', $fileNameArray) . ".$fileExtension"));

                    $productMockupFile->file_name = $fileName;
                    $productMockupFile->save();
                    $uploadUrl = $productMockupFile->file_path;
                    //$productMockupFile->file_name = null;

//                    $this->productVariantMockupFile->file_name = $fileName;
//                    $uploadUrl = $this->productVariantMockupFile->file_path;
//                    $this->productVariantMockupFile->file_name = null;

                    $dataToPostAr['images'][] = array(
                        'code' => $blankPsd->getDatedFileName(),
                        'fileUrl' => $blankPsd->getFileUrlOriginal(),
                        'artUrls' => $artUrls,
                        'displayLayers' => $displayLayers,
                        'uploadUrl' => $uploadUrl,
                        'bucketName' => config('filesystems.disks.s3.bucket')
                    );
                } else {
                    $dataToPostAr['images'][] = array(
                        'code' => $blankPsd->getDatedFileName(),
                        'fileUrl' => $blankPsd->getFileUrlOriginal(),
                        'artUrls' => $artUrls,
                        'displayLayers' => $displayLayers
                    );
                }

                $this->logger->debug('$dataToPostAr:' . json_encode($dataToPostAr, JSON_UNESCAPED_SLASHES));


                $mockupResponse = $this->requestMockup($dataToPostAr);
                $mockupResponse = json_decode(json_encode($mockupResponse), true);

                if (isset($mockupResponse['error'])) {
                    $message = $mockupResponse['error'];
                    $this->logger->error($message);
                    $mockupErrors[] = $message;
                } else {
                    try {
                        $mockupTmpPath = $sendFilePathToPsd ? $uploadUrl : $mockupResponse['images'][0];

                        //Save product mockup file
                        $productMockupFile->processed_url = $mockupTmpPath;
                        $productMockupFile->status = ProductMockupFile::STATUS_REQUESTED;
                        $productMockupFile->save();

                        if ($batchMatching) {
                            //Update matching
                            if ($mockupsToProcess) {
                                $this->logger->debug("Mockups to Update: " . json_encode($mockupsToProcess->pluck('id')));
                                ProductVariantMockupFile::whereIn('id', $mockupsToProcess->pluck('id'))->update([
                                    'processed_url' => $mockupTmpPath,
                                    'status' => ProductVariantMockupFile::STATUS_REQUESTED,
                                    'product_mockup_file_id' => $productMockupFile->id
                                ]);
                            }
                        } else {
                            $this->productVariantMockupFile->processed_url = $mockupTmpPath;
                            $this->productVariantMockupFile->status = ProductVariantMockupFile::STATUS_REQUESTED;
                            $this->productVariantMockupFile->save();
                        }

                    } catch (Exception $e) {
                        $this->fail($e);
                    }
                }
            }
        }

        $this->productVariantMockupFile->refresh();
        $this->logger->debug('Product Variant Mockup File ID ' . $this->productVariantMockupFile->id . 'status: ' . $this->productVariantMockupFile->status);
        if ($this->productVariantMockupFile->status === ProductVariantMockupFile::STATUS_REQUESTED) {
            $this->logger->debug('Kick off RetrieveMockupFile');
            if (config('app.env') === 'local') {
                RetrieveMockupFile::dispatch($this->productVariantMockupFile)->delay(15);
            } else {
                RetrieveMockupFile::dispatch($this->productVariantMockupFile)->onQueue('mockup-files')->delay(60);
            }
        }
        $this->logger->title("END ProcessProductVariantMockupFile | ID: {$this->productVariantMockupFile->id}");
    }

    /**
     * The job failed to process.
     *
     * @param Exception|Error $exception
     * @return void
     */
    public function failed($exception)
    {
        if ($this->attempts() >= $this->tries) {
            $this->productVariantMockupFile->status = ProductVariantMockupFile::STATUS_FAILED;
            $this->productVariantMockupFile->save();
            parent::failed($exception);
        }
    }

    function requestMockup($dataToPostAr)
    {
        $token = config('app.mockup_server_token');
        $userId = 1;

        $curlType = 'POST';

        $postUrl = 'http://teeprocessing.teelaunch.com/imageprocess8/queuePSImage.php';

        if (config('app.env') == 'local') {
            $devServer = config('app.dev_mockup_server_url');
            $this->logger->debug("Dev Server: $devServer");
            $postUrl = $devServer . '/imageprocess8/queuePsImage.php';
            $this->logger->debug("Local Environment sending to $postUrl");
        }

        $dataToPost = http_build_query($dataToPostAr);

        $this->logger->info("Sending to server url: $postUrl");

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $postUrl);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            'Content-Type: application/x-www-form-urlencoded',
            'Sunrise-Access-Token: ' . $token,
            'Sunrise-User: ' . intval($userId)
        ));
        curl_setopt($ch, CURLOPT_VERBOSE, 0);
        curl_setopt($ch, CURLOPT_HEADER, 0);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, '0');
        if ($curlType == 'PUT') {
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "PUT");
        }
        if ($curlType == 'DELETE') {
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "DELETE");
        }
        if ($curlType == 'POST') {
            curl_setopt($ch, CURLOPT_POST, 1);
        }
        if ($curlType == 'GET') {
            curl_setopt($ch, CURLOPT_POST, 0);
        }
        if ($dataToPost) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $dataToPost);
        }
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
        curl_setopt($ch, CURLOPT_TIMEOUT, 60); //timeout in seconds

        $response = curl_exec($ch);

        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        $this->logger->info("Mockup Server Response: $response | HTTP $httpCode");

        $r = json_decode($response);

        if (is_object($r)) {
            return $r;
        }

        return false;
    }

    public function checkResourceUsage($file)
    {
        $imagick = new Imagick();


        $resource = fopen($file, 'r');

        $imagick->readImageFile($resource);

        $this->logger->info("Undefined:" . $imagick->getResourceLimit(Imagick::RESOURCETYPE_UNDEFINED));

        $this->logger->info("Area: " . $imagick->getResourceLimit(Imagick::RESOURCETYPE_AREA));

        $this->logger->info("Disk: " . $imagick->getResourceLimit(Imagick::RESOURCETYPE_DISK));

        $this->logger->info("File: " . $imagick->getResourceLimit(Imagick::RESOURCETYPE_FILE));

        $this->logger->info("Map: " . $imagick->getResourceLimit(Imagick::RESOURCETYPE_MAP));

        $this->logger->info("Memory: " . $imagick->getResourceLimit(Imagick::RESOURCETYPE_MEMORY));

        //Doesnt seem to work
//        Imagick::setResourceLimit(Imagick::RESOURCETYPE_AREA, $imagick->getResourceLimit(Imagick::RESOURCETYPE_AREA) * 2);
//        Imagick::setResourceLimit(Imagick::RESOURCETYPE_DISK, $imagick->getResourceLimit(Imagick::RESOURCETYPE_DISK) * 2);
//        Imagick::setResourceLimit(Imagick::RESOURCETYPE_FILE, $imagick->getResourceLimit(Imagick::RESOURCETYPE_FILE) * 2);
//        Imagick::setResourceLimit(Imagick::RESOURCETYPE_MAP, $imagick->getResourceLimit(Imagick::RESOURCETYPE_MAP) * 2);
//        Imagick::setResourceLimit(Imagick::RESOURCETYPE_MEMORY, $imagick->getResourceLimit(Imagick::RESOURCETYPE_MEMORY) * 2);
    }
}
