<?php

namespace App\Jobs;

use App\Logger\ConnectorLoggerFactory;
use App\Models\Blanks\BlankPsd;
use App\Models\FileModel;
use App\Models\Products\ProductArtFile;
use App\Models\Products\ProductMockupFile;

use App\Models\Products\ProductVariant;
use App\Models\Products\ProductVariantMockupFile;
use App\Models\Products\ProductVariantStageFile;
use Exception;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Imagick;
use Intervention\Image\ImageManager;

class ProcessProductMockupFile extends BaseJob
{
  protected $accountId;

  /**
   * @var ProductMockupFile
   */
  protected $productMockupFile;

  public $logChannel = 'mockup-files';

  protected $uploadUrl;

  /**
   * Create a new job instance.
   *
   * @param ProductMockupFile $productMockupFile
   */
  public function __construct(ProductMockupFile $productMockupFile)
  {
    parent::__construct();
    $this->productMockupFile = $productMockupFile;
    $this->accountId = $this->productMockupFile->account_id;
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
      $this->productMockupFile->account_id,
      'system',
      $this->processId);
    $this->logger = $loggerFactory->getLogger();

    $this->logger->title("Start ProcessProductMockupFile | ID: {$this->productMockupFile->id} | Product ID: {$this->productMockupFile->product_id} |  Attempt: {$this->attempts()}/{$this->tries}");

    if (!isset($this->productMockupFile->product) || $this->productMockupFile->product->deleted_at) {
      $this->logger->info("Product has been deleted, abort process");
      return;
    }

    $this->productMockupFile->status = ProductMockupFile::STATUS_STARTED;
    $this->productMockupFile->started_at = Carbon::now();
    $this->productMockupFile->save();


    $mockupRequestData = $this->buildMockupData();

    if ($mockupRequestData) {
      $mockupResponse = $this->requestMockup($mockupRequestData);
      $mockupResponse = json_decode(json_encode($mockupResponse), true);

      if (isset($mockupResponse['error'])) {
        $message = $mockupResponse['error'];
        $this->logger->error($message);
        $mockupErrors[] = $message;
      } else {
        try {
          //Save product mockup file
          $this->productMockupFile->processed_url = $this->uploadUrl;
          $this->productMockupFile->status = ProductMockupFile::STATUS_REQUESTED;
          $this->productMockupFile->save();

          RetrieveMockupFile::dispatch($this->productMockupFile);
        } catch (Exception $e) {
          $this->fail($e);
        }
      }
    }


    if ($this->productMockupFile->status !== ProductMockupFile::STATUS_REQUESTED) {
      if ($this->attempts() < $this->tries) {
        $this->logger->warning("File already in location ");
        $this->release($this->delay);
        return;
      }
      $this->productMockupFile->finished_at = Carbon::now();
      $this->productMockupFile->status = ProductMockupFile::STATUS_FAILED;
      $this->productMockupFile->save();
    }


    //Dispatch ProductVariantMockupFiles
    foreach ($this->productMockupFile->productVariantMockupFiles as $productVariantMockupFile) {
      //$this->logger->info("Dispatching ProcessProductVariantMockupFile ID $productVariantMockupFile->id");
      //ProcessProductVariantMockupFile::dispatch($productVariantMockupFile);
    }

    $this->logger->title("End ProcessProductMockupFile | ID: {$this->productMockupFile->id} | Product ID: {$this->productMockupFile->product_id} |  Attempt: {$this->attempts()}/{$this->tries}");
  }

  public function buildMockupData()
  {
    $this->logger->info("Requesting mockup...");

    if ($this->productMockupFile->processed_url) {
      $this->logger->info("Mockup already requested");
      return null;
    }

    $blankPsds = BlankPsd::where([['id', $this->productMockupFile->blank_psd_id], ['is_active', true]])->with('layers')->get();
    if (!$blankPsds) {
      $this->fail(new Exception("No active Blank PSD found"));
    }

    $this->logger->info("Found " . count($blankPsds) . " Blank PSD Files");

    foreach ($blankPsds as $blankPsd) {

      $displayLayers = [];
      $artUrls = [];
      $fileExtension = 'png';

      $this->logger->info("Found " . count($blankPsd->layers) . " Blank PSD Layers");
      $this->logger->debug("Blank PSD Layers: " . json_encode($blankPsd->layers));

      foreach ($blankPsd->layers as $blankPsdLayer) {
        $isDisplayLayer = empty($blankPsdLayer->blank_stage_id);

        if ($isDisplayLayer) {
          $this->logger->info("Add display layer: $blankPsdLayer->layer_name");
          $displayLayers[] = $blankPsdLayer->layer_name;
        } else {
          //Resize stage_file
          $stageFileQuery = ProductVariantStageFile::where([['product_id', $this->productMockupFile->product_id], ['blank_stage_group_id', $blankPsd->blank_stage_group_id], ['blank_stage_id', $blankPsdLayer->blank_stage_id]]);

          $this->logger->debug('stageFileQuery:' . $stageFileQuery->toSql() . " | " . json_encode($stageFileQuery->getBindings()));

          $stageFileDb = $stageFileQuery->first();
          $this->logger->debug('$stageFileDb:' . json_encode($stageFileDb));

          $stageFile = $stageFileDb;
          $this->logger->debug('$stageFile:' . json_encode($stageFile));

//                  if (!$stageFile) {
//                    $this->fail(new Exception("No stage file found"));
//                  }

          $mockupArtPath = null;
          if ($stageFile) {
            // $artFile = $this->productMockupFile->productArtFile;

            $artFile = $stageFile->productArtFile;


            //Allow reuse of image by grouping the image
            $mockupArtDir = "accounts/" . $this->productMockupFile->account_id . "/mockup-layers/" . $this->productMockupFile->product_id . "/" . $this->productMockupFile->blank_stage_id;
            $stageFileName = $artFile->file_name;
            $stageFileName = str_replace('.jpg', '.png', $stageFileName);
            $mockupArtPath = "$mockupArtDir/$stageFileName";

            if (!Storage::disk('s3-nocache')->exists($mockupArtPath)) {

              $this->logger->debug('$artFile:' . json_encode($artFile, JSON_PRETTY_PRINT));

              $manager = new ImageManager(array('driver' => 'imagick'));

              $blankStageLocationSubSettings = $stageFile->blankStageLocationSubSettings;
              $this->logger->debug('$blankStageLocationSubSettings:' . json_encode($blankStageLocationSubSettings, JSON_PRETTY_PRINT));

              $resizeAndPosition = !empty($blankStageLocationSubSettings);

              $category = $this->productMockupFile->blank->category;
              $this->logger->debug('Blank Category: ' . $category->name);

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
          }

          $tempUrl = null;
          if ($mockupArtPath) {
            //TODO: This needs to take into account local or cloud storage
            $tempUrl = Storage::temporaryUrl($mockupArtPath, Carbon::now()->addMinutes(600));

            if (!$tempUrl) {
              $this->logger->error("No file url exists");
              $this->fail(new Exception('File url could not be generated'));
            }
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


      $productVariant = ProductVariant::with('product', 'blankVariant', 'blankVariant.optionValues')->find($this->productMockupFile->product_variant_id);

      $blankVariantOptionValues = [];
      foreach ($productVariant->blankVariant['optionValues'] as $blankVariantOptionValue) {
        if (stripos($blankVariantOptionValue->option->name, 'size') === false) {
          $blankVariantOptionValues[] = str_replace(' ', '_', $blankVariantOptionValue['name']);
        }
      }
      $productName = $this->productMockupFile->product->name;
      $fileNameArray[] = substr($productName,0,40);
      $fileNameArray[] = ucwords($blankPsd->name);
      $tmpFileName = substr(implode('_', $fileNameArray), 0, 80).'_Mockup';
      $fileName = FileModel::sanitizeFileName(str_replace(' ', '_', $tmpFileName . ".$fileExtension"));

      $this->productMockupFile->file_name = $fileName;
      $this->uploadUrl = $this->productMockupFile->file_path;
      $this->productMockupFile->file_name = null;

      $dataToPostAr['images'][] = array(
        'code' => $blankPsd->getDatedFileName(),
        'fileUrl' => $blankPsd->getFileUrlOriginal(),
        'artUrls' => $artUrls,
        'displayLayers' => $displayLayers,
        'uploadUrl' => $this->uploadUrl,
        'bucketName' => config('filesystems.disks.s3.bucket')
      );


      $this->logger->debug('$dataToPostAr:' . json_encode($dataToPostAr, JSON_UNESCAPED_SLASHES));
      return $dataToPostAr;
    }

    return null;
  }

  function requestMockup($dataToPostAr)
  {
    $token = config('app.mockup_server_token');
    $userId = 1;

    $curlType = 'POST';

    $postUrl = 'http://teeprocessing.teelaunch.com/imageprocess8/queuePSImage.php';

    if (config('app.env') !== 'production') {
      $devServer = config('app.dev_mockup_server_url');
      $this->logger->debug("Dev PSD Server: $devServer");
      $postUrl = $devServer . '/imageprocess8/queuePsImage.php';
      $this->logger->debug("Non Production Environment sending to $postUrl");
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

  public function removeWhiteSpace($image, $isApparel)
  {
    $this->logger->subheader("Remove White Space");

    if (!$isApparel) {
      $this->logger->info("Skip trim, not apparel");
      return $image;
    }
    //Strip white space
    $hexcolor = $image->pickColor(0, 0, 'hex');
    $this->logger->info("Hex color at (0,0): $hexcolor");
    if ($hexcolor === '#ffffff') {
      $this->logger->info("Trimming image");
      $image->trim('top-left');


//            $this->logger->debug("Create Mask");
//            $mask = $manager->make($image)->greyscale()->limitColors(2)->contrast(100)->invert();
//            $image = $mask;

      // $image->mask($mask);
    }
    return $image;
  }

}
