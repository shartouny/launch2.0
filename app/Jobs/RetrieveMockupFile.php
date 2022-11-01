<?php

namespace App\Jobs;

use App\Logger\ConnectorLoggerFactory;
use App\Models\Products\PlatformProductQueue;
use App\Models\Products\ProductVariant;
use App\Models\Products\ProductMockupFile;
use App\Models\Products\ProductVariantMockupFile;
use App\Models\Products\ProductVariantStageFile;
use Exception;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\ImageManager;

class RetrieveMockupFile extends BaseJob
{
    public $tries = 10;
    public $retryAfter = 60;
    public $timeout = 1200;

    /**
     * @var ProductMockupFile
     */
    protected $productMockupFile;
    public $logChannel = 'mockup-files';

    /**
     * Create a new job instance.
     *
     * @param ProductMockupFile $productMockupFile
     */
    public function __construct(ProductMockupFile $productMockupFile)
    {
        parent::__construct();
        $this->productMockupFile = $productMockupFile;

        if (config('app.env') === 'local') {
            $this->retryAfter = 15;
        }
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $loggerFactory = new ConnectorLoggerFactory(
            $this->logChannel,
            $this->productMockupFile->account_id,
            'system');
        $this->logger = $loggerFactory->getLogger();

        $this->logger->info("-------------------- Start RetrieveMockupFile | ID: {$this->productMockupFile->id} | Attempt: {$this->attempts()}/{$this->tries} | Retry Count: {$this->productMockupFile->retry_count} --------------------");

        if (!isset($this->productMockupFile->product) || $this->productMockupFile->product->deleted_at) {
            $this->logger->info("Product Variant has been deleted, abort process");
            return;
        }

        $this->logger->info("Attempting to access file: {$this->productMockupFile->processed_url}");

        $content = null;
        $httpCode = null;

        $fullUrl = Storage::disk('s3-nocache')->url($this->productMockupFile->processed_url);
        $this->logger->info("File URL: " . $fullUrl);

        $fileExists = Storage::disk('s3-nocache')->exists($this->productMockupFile->processed_url);
        $this->logger->info("File Exists: " . ($fileExists ? 'true' : 'false'));
        if (!$fileExists) {
            $this->logger->info("Trying to check for image using cUrl...");
            try {
                $ch = curl_init($fullUrl);
                curl_setopt($ch, CURLOPT_TIMEOUT, 15);
                curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                $content = curl_exec($ch);
                $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                $contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
                curl_close($ch);
                $this->logger->info("Check for Image | HTTP $httpCode");
            } catch (Exception $e) {
                $this->logger->error($e->getMessage());
            }
        }

        if (!$fileExists && $content && $httpCode == 200) {
            $this->logger->info("File driver couldn't find the file yet cURL did");

            $this->logger->info("Check again using S3-nocache driver...");
            if (Storage::disk('s3-nocache')->exists($this->productMockupFile->processed_url)) {
                $this->logger->info("File exists using S3-nocache");
                $fileExists = true;
            } else {
                $this->logger->info("File does not exist using S3-nocache");

                $this->logger->info("Flushing cache...");
                Cache::flush();
                if (Storage::exists($this->productMockupFile->processed_url)) {
                    $this->logger->info("File exists after flushing cache");
                    $fileExists = true;
                } else {
                    $this->logger->info("File does not exist after flushing cache");
                }
            }
        }

        if ($fileExists) {
            $this->logger->info("File exists");
            $this->productMockupFile->file_name = basename($this->productMockupFile->processed_url);
            $this->productMockupFile->status = ProductVariantStageFile::STATUS_FINISHED;
            $this->productMockupFile->finished_at = Carbon::now();
            $this->productMockupFile->save();

            $this->logger->info("Set File Name to " . basename($this->productMockupFile->processed_url));

            if ($this->productMockupFile->getGenerateThumbnail()) {

                $thumbnailPath = $this->productMockupFile->getThumbnailPath();
                $manager = new ImageManager(array('driver' => 'imagick'));

                $thumbnailFile = $manager->make($this->productMockupFile->getFileUrlOriginal())
                    ->resize($this->productMockupFile->getThumbnailWidth(), $this->productMockupFile->getThumbnailWidth(), function ($constraint) {
                        $constraint->aspectRatio();
                    })->encode();

                $this->logger->info("Saving thumbnail $thumbnailPath");
                $isSuccess = Storage::put($thumbnailPath, $thumbnailFile);
                $this->logger->info("Response: " . json_encode($isSuccess));

            }


            $this->productMockupFile->productVariantMockupFiles()->update([
                'status' => ProductVariantMockupFile::STATUS_FINISHED,
                'finished_at' => Carbon::now()
            ]);

        } else {
            if ($this->attempts() < $this->tries) {
                $releaseTime = $this->retryAfter;
                $this->logger->info("Retry Job after {$releaseTime}s");
                $this->release($releaseTime);
                return;
            } else {
                if ($this->productMockupFile->retry_count < 3) {
                    $this->requestNewMockup();
                    return;
                }

                $this->productMockupFile->status = ProductVariantStageFile::STATUS_FAILED;
                $this->productMockupFile->save();
                $this->logger->info("Failed after too many attempts");
            }
        }

        //Process the Platform Product if no pending ProductMockupFile
        $remainingCount = ProductMockupFile::where('product_id', $this->productMockupFile->product_id)->where(function ($q) {
            return $q->where('status', '<', ProductMockupFile::STATUS_FINISHED);
        })->count();

        if ($remainingCount == 0) {
            $platformProductQueues = PlatformProductQueue::where([['product_id', $this->productMockupFile->product_id], ['status', PlatformProductQueue::STATUS_PENDING]])->get();
            foreach ($platformProductQueues as $platformProductQueue) {
                if (config('app.env') === 'local') {
                    ProcessPlatformProductQueue::dispatch($platformProductQueue);
                } else {
                    ProcessPlatformProductQueue::dispatch($platformProductQueue)->onQueue('products');
                }
            }
        }
    }

    private function requestNewMockup()
    {
        $this->productMockupFile->retry_count++;
        $this->productMockupFile->deleteFile();
        $this->productMockupFile->status = ProductMockupFile::STATUS_PENDING;
        $this->productMockupFile->finished_at = null;
        $this->productMockupFile->processed_url = null;
        $this->productMockupFile->save();

        if (config('app.env') === 'local') {
            ProcessProductMockupFile::dispatch($this->productMockupFile);
        } else {
            ProcessProductMockupFile::dispatch($this->productMockupFile)->onQueue('mockup-files');
        }
    }

}
