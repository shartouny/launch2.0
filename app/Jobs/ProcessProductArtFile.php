<?php

namespace App\Jobs;

use App\Logger\ConnectorLoggerFactory;
use App\Models\Products\ProductArtFile;

use App\Models\Products\ProductVariantStageFile;
use Exception;
use Illuminate\Support\Carbon;
use Imagick;
use Intervention\Image\ImageManager;

class ProcessProductArtFile extends BaseJob
{
    /**
     * @var int
     */
    protected $accountId;

    /**
     * @var ProductArtFile
     */
    protected $productArtFile;

    /**
     * @var ImageManager
     */
    protected $imageManager;

    /**
     * @var bool
     */
    protected $isApparel = false;

    /**
     * @var string
     */
    public $logChannel = 'art-files';

    /**
     * Create a new job instance.
     *
     * @param ProductArtFile $productArtFile
     */
    public function __construct(ProductArtFile $productArtFile)
    {
        parent::__construct();
        $this->productArtFile = $productArtFile;
        $this->accountId = $this->productArtFile->account_id;

        $blank = $this->productArtFile->blank;
        $category = $blank->category;
        $this->isApparel = strtolower($category->name) == 'apparel';

        $this->imageManager = new ImageManager(array('driver' => 'imagick'));
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
            $this->productArtFile->account_id,
            'system',
            $this->processId);
        $this->logger = $loggerFactory->getLogger();

        $this->logger->title("Start ProcessProductArtFile | ID: {$this->productArtFile->id} | Product ID: {$this->productArtFile->product_id} |  Attempt: {$this->attempts()}/{$this->tries}");

        if (!isset($this->productArtFile->product) || $this->productArtFile->product->deleted_at) {
            $this->logger->info("Product has been deleted, abort process");
            return;
        }

        $accountImage = $this->productArtFile->accountImage;
        if (!$accountImage) {
            $this->logger->info("Account Image has been deleted, abort process");
            return;
        }

        $this->logger->debug("Is apparel? " . ($this->isApparel ? 'true' : 'false'));

        $this->productArtFile->status = ProductArtFile::STATUS_STARTED;
        $this->productArtFile->started_at = Carbon::now();
        $this->productArtFile->image_type_id = $accountImage->image_type_id;
        $this->productArtFile->width = $accountImage->width;
        $this->productArtFile->height = $accountImage->height;
        $this->productArtFile->size = $accountImage->size;
        $this->productArtFile->save();

        if ($this->createFile($accountImage)) {
            $this->productArtFile->finished_at = Carbon::now();
            $this->productArtFile->status = ProductArtFile::STATUS_FINISHED;
            $this->productArtFile->save();
        } else {
            if ($this->attempts() < $this->tries) {
                $this->release($this->delay);
                return;
            }
            $this->productArtFile->finished_at = Carbon::now();
            $this->productArtFile->status = ProductArtFile::STATUS_FAILED;
            $this->productArtFile->save();
            $this->logger->error("ProcessProductArtFile Failed");
            return;
        }

        //Dispatch ProductVariantStageFiles

        $this->logger->info("Found " . count($this->productArtFile->productVariantStageFiles) . " ProductVariantStageFiles");
        foreach ($this->productArtFile->productVariantStageFiles as $productVariantStageFile) {
            $this->logger->info("Dispatching ProcessProductVariantStageFile ID $productVariantStageFile->id");

            if (config('app.env') === 'local') {
                ProcessProductVariantStageFile::dispatch($productVariantStageFile);
            } else {
                ProcessProductVariantStageFile::dispatch($productVariantStageFile)->onQueue('stage-files');
            }

        }

        $this->logger->title("End ProcessproductVariantArtFile | ID: {$this->productArtFile->id} | Product ID: {$this->productArtFile->product_id} |  Attempt: {$this->attempts()}/{$this->tries}");
    }

    /**
     * @param $accountImage
     * @return bool
     */
    public function createFile($accountImage)
    {
        $this->productArtFile->file_name = $accountImage->file_name;
        $this->logger->info("Copying file from {$accountImage->file_path} to {$this->productArtFile->file_path}");
        try {
            if($this->isApparel){
                $image = $this->imageManager->make($accountImage->file_url_original);
                $this->trimWhiteSpace($image, $accountImage);
                if(!$this->productArtFile->saveFile($image->encode(), $accountImage->file_name, null, $makePublic = true)){
                    $this->logger->info("Failed to save image");
                    return false;
                }
            } else {
                if (!$this->productArtFile->copyFile($accountImage->file_path, $accountImage->file_name)) {
                    $this->logger->info("Failed to copy");
                    return false;
                }
            }
        } catch (Exception $e) {
            $this->logger->error($e);
            return false;
        }

        $this->logger->info("File copied");

        return true;
    }

    public function trimWhiteSpace($image, $artFile)
    {
      if(config('app.env') === 'local'){
        return $image;
      }

      try {
        $this->logger->info("Trim whitespace");

        $expand = 5;
        $image->resizeCanvas($image->width() + $expand, $image->height() + $expand);

        $quantumRange = Imagick::getQuantumRange();
        $percentFuzz = 0.1;
        $trimFuzz = $quantumRange['quantumRangeLong'] * $percentFuzz;
        $imagick = $image->getCore(); //get Imagick object
        $imagick->trimImage($trimFuzz);

        $tmpFileName = 'art-file-' . $this->productArtFile->id . "-" . $artFile->file_name;
        $tmpFileDir = storage_path("app/tmp/");
        $tmpFilePath = $tmpFileDir . $tmpFileName;
        file_put_contents($tmpFilePath, $imagick);

        $image = $this->imageManager->make($tmpFilePath);

        unlink($tmpFilePath);
      }catch (Exception $e){
        $this->logger->error($e);
      }
        return $image;
    }
}
