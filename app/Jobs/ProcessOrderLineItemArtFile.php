<?php

namespace App\Jobs;

use App\Logger\ConnectorLoggerFactory;
use App\Models\Orders\OrderLineItemArtFile;
use App\Models\Orders\OrderLineItemPrintFile;
use App\Models\Products\ProductArtFile;

use App\Models\Products\ProductPrintFile;
use Exception;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Imagick;
use Intervention\Image\ImageManager;

class ProcessOrderLineItemArtFile extends BaseJob
{
    /**
     * @var int
     */
    protected $accountId;

    /**
     * @var ImageManager
     */
    protected $imageManager;

    /**
     * @var bool
     */
    protected $isApparel = false;

    /**
     * @var OrderLineItemArtFile
     */
    protected $orderLineItemArtFile;
    public $logChannel = 'art-files';

    /**
     * Create a new job instance.
     *
     * @param OrderLineItemArtFile $orderLineItemArtFile
     */
    public function __construct(OrderLineItemArtFile $orderLineItemArtFile)
    {
        parent::__construct();
        $this->orderLineItemArtFile = $orderLineItemArtFile;
        $this->accountId = $this->orderLineItemArtFile->account_id;

        $blank = $this->orderLineItemArtFile->blank;
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
            $this->orderLineItemArtFile->account_id,
            'system',
            $this->processId);
        $this->logger = $loggerFactory->getLogger();

        $this->logger->title("Start ProcessOrderLineItemArtFile | ID: {$this->orderLineItemArtFile->id} | Product ID: {$this->orderLineItemArtFile->product_id} |  Attempt: {$this->attempts()}/{$this->tries}");

        if (!isset($this->orderLineItemArtFile->product) || $this->orderLineItemArtFile->product->deleted_at) {
            $this->logger->info("Product has been deleted, abort process");
            return;
        }

        $accountImage = $this->orderLineItemArtFile->accountImage;
        if (!$accountImage) {
            $this->logger->info("Account Image has been deleted, abort process");
            return;
        }

        $this->orderLineItemArtFile->status = ProductArtFile::STATUS_STARTED;
        $this->orderLineItemArtFile->started_at = Carbon::now();
        $this->orderLineItemArtFile->image_type_id = $accountImage->image_type_id;
        $this->orderLineItemArtFile->width = $accountImage->width;
        $this->orderLineItemArtFile->height = $accountImage->height;
        $this->orderLineItemArtFile->size = $accountImage->size;
        $this->orderLineItemArtFile->save();

        $printFiles = $this->orderLineItemArtFile->product->printFiles()->where('blank_id', $this->orderLineItemArtFile->blank_id)->get() ?? [];

        //Delete existing OrderLineItemPrintFile to prevent Job from running when uploading multiple art files
//        foreach ($printFiles as $printFile) {
//            $orderLineItemPrintFile = OrderLineItemPrintFile::where([['order_id', $this->orderLineItemArtFile->order_id], ['order_line_item_id', $this->orderLineItemArtFile->order_line_item_id], ['product_print_file_id', $printFile->id]])->first();
//            if ($orderLineItemPrintFile) {
//                $this->logger->debug("Delete existing OrderLineItemPrintFile {$orderLineItemPrintFile->id}");
//                $orderLineItemPrintFile->delete();
//            }
//        }

        if ($this->createFile($accountImage)) {
            $this->orderLineItemArtFile->finished_at = Carbon::now();
            $this->orderLineItemArtFile->status = ProductArtFile::STATUS_FINISHED;
            $this->orderLineItemArtFile->save();
        } else {
            if ($this->attempts() < $this->tries) {
                $this->release($this->delay);
                return;
            }
            $this->orderLineItemArtFile->finished_at = Carbon::now();
            $this->orderLineItemArtFile->status = ProductArtFile::STATUS_FAILED;
            $this->orderLineItemArtFile->save();
            $this->logger->error("ProcessProductArtFile Failed");
            return;
        }

        $this->logger->debug("Found ".count($printFiles)." print files to update");
        foreach ($printFiles as $printFile) {
            $orderLineItemPrintFile = OrderLineItemPrintFile::where([['order_id', $this->orderLineItemArtFile->order_id], ['order_line_item_id', $this->orderLineItemArtFile->order_line_item_id], ['product_print_file_id', $printFile->id]])->first();

            if (!$orderLineItemPrintFile) {
                $orderLineItemPrintFile = new OrderLineItemPrintFile();
                $orderLineItemPrintFile->account_id = $this->orderLineItemArtFile->account_id;
                $orderLineItemPrintFile->order_id = $this->orderLineItemArtFile->order_id;
                $orderLineItemPrintFile->order_line_item_id = $this->orderLineItemArtFile->order_line_item_id;
                $orderLineItemPrintFile->product_print_file_id = $printFile->id;
            }

            $orderLineItemPrintFile->product_id = $printFile->product_id;
            $orderLineItemPrintFile->product_print_file_id = $printFile->id;

            $orderLineItemPrintFile->order_line_item_art_file_id = $this->orderLineItemArtFile->id;
            $orderLineItemPrintFile->account_image_id = $this->orderLineItemArtFile->account_image_id;

            $orderLineItemPrintFile->blank_print_image_id = $printFile->blank_print_image_id;
            $orderLineItemPrintFile->blank_stage_id = $printFile->blank_stage_id;
            $orderLineItemPrintFile->blank_stage_location_id = $printFile->blank_stage_location_id;
            $orderLineItemPrintFile->blank_id = $printFile->blank_id;
            $orderLineItemPrintFile->status = ProductPrintFile::STATUS_PENDING;
            $orderLineItemPrintFile->save();

            if (config('app.env') === 'local') {
                ProcessOrderLineItemPrintFile::dispatch($orderLineItemPrintFile);
            } else {
                ProcessOrderLineItemPrintFile::dispatch($orderLineItemPrintFile)->delay(now()->addMinutes(5))->onQueue('print-files');
            }
        }

        $this->logger->title("End ProcessOrderLineItemArtFile | ID: {$this->orderLineItemArtFile->id} | Product ID: {$this->orderLineItemArtFile->product_id} |  Attempt: {$this->attempts()}/{$this->tries}");
    }

    /**
     * @param $accountImage
     * @return bool
     */
    public function createFile($accountImage)
    {
        $this->orderLineItemArtFile->file_name = $accountImage->file_name;
        $this->logger->info("Copying file from {$accountImage->file_path} to {$this->orderLineItemArtFile->file_path}");
        try {
            if($this->isApparel){
                $image = $this->imageManager->make($accountImage->file_url_original);
                $this->trimWhiteSpace($image, $accountImage);
                if(!$this->orderLineItemArtFile->saveFile($image->encode(), $accountImage->file_name, null, $makePublic = true)){
                    $this->logger->info("Failed to save image");
                    return false;
                }
            } else {
                if (!$this->orderLineItemArtFile->copyFile($accountImage->file_path, $accountImage->file_name)) {
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
        $this->logger->info("Trim whitespace");

        $expand = 5;
        $image->resizeCanvas($image->width() + $expand, $image->height() + $expand);

        $quantumRange = Imagick::getQuantumRange();
        $percentFuzz = 0.1;
        $trimFuzz = $quantumRange['quantumRangeLong'] * $percentFuzz;
        $imagick = $image->getCore(); //get Imagick object
        $imagick->trimImage($trimFuzz);

        $tmpFileName = 'line-item-art-file-' . $this->orderLineItemArtFile->id . "-" . $artFile->file_name;
        $tmpFileDir = storage_path("app/tmp/");
        $tmpFilePath = $tmpFileDir . $tmpFileName;
        file_put_contents($tmpFilePath, $imagick);

        $image = $this->imageManager->make($tmpFilePath);

        //unlink($tmpFilePath);

        return $image;
    }
}
