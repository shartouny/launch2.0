<?php

namespace App\Jobs;

use App\Logger\ConnectorLoggerFactory;
use App\Models\Blanks\BlankPrintImage;
use App\Models\Blanks\BlankPsd;
use App\Models\Products\ProductArtFile;
use App\Models\Products\ProductMockupFile;
use App\Models\Products\ProductVariantMockupFile;
use Error;
use Exception;
use App\Models\Accounts\AccountImage;
use App\Traits\PrintFileJobsCreation;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use SunriseIntegration\TeelaunchModels\Models\Products\ProductVariantStageFile;

class ProcessProductVariantStageFile extends BaseJob
{
    use PrintFileJobsCreation;

    protected $accountId;

    public $tries = 10;

    public $retryAfter = 30;

    /**
     * @var ProductVariantStageFile
     */
    protected $productVariantStageFile;
    public $logChannel = 'stage-files';

    /**
     * Create a new job instance.
     *
     * @param ProductVariantStageFile $productVariantStageFile
     */
    public function __construct(ProductVariantStageFile $productVariantStageFile)
    {
        parent::__construct();
        $this->productVariantStageFile = $productVariantStageFile;
        $this->accountId = $this->productVariantStageFile->account_id;
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
            $this->productVariantStageFile->account_id,
            'system',
            $this->processId);
        $this->logger = $loggerFactory->getLogger();

        $this->logger->title("Start ProcessProductVariantStageFile | ID: {$this->productVariantStageFile->id} | Product ID: {$this->productVariantStageFile->product_id} |  Attempt: {$this->attempts()}/{$this->tries}");
        $this->logger->info("Product Variant ID: " . $this->productVariantStageFile->product_variant_id);

        if (!isset($this->productVariantStageFile->product) || !isset($this->productVariantStageFile->productVariant) || $this->productVariantStageFile->product->deleted_at || $this->productVariantStageFile->productVariant->deleted_at) {
            $this->logger->info("Product Variant has been deleted, abort process");
            return;
        }

        $this->logger->info("ProductArtFile status:" . $this->productVariantStageFile->productArtFile->status);
        if ($this->productVariantStageFile->productArtFile->status !== ProductArtFile::STATUS_FINISHED) {
            $this->logger->info("Product Variant Art File still processing, retry in {$this->retryAfter}s...");
            $this->release($this->retryAfter);
            return;
        }

        $accountImage = AccountImage::find($this->productVariantStageFile->account_image_id);
        if (!$accountImage) {
            $this->logger->warning("Account image not found, retry in {$this->retryAfter}s...");
            $this->release($this->retryAfter);
            return;
        }

        $this->productVariantStageFile->status = ProductVariantStageFile::STATUS_STARTED;
        $this->productVariantStageFile->started_at = Carbon::now();
        $this->productVariantStageFile->image_type_id = $accountImage->image_type_id;
        $this->productVariantStageFile->width = $accountImage->width;
        $this->productVariantStageFile->height = $accountImage->height;
        $this->productVariantStageFile->size = $accountImage->size;
        $this->productVariantStageFile->save();

        $blankId = $this->productVariantStageFile->blank_id;
        $blankStageGroupId = $this->productVariantStageFile->blank_stage_group_id;
        $blankStageId = $this->productVariantStageFile->blank_stage_id;

        //----- Handle files that apply to all variants -----//
        $blankPsdQuery = BlankPsd::where([
            ['blank_id', $blankId],
            ['blank_stage_group_id', $blankStageGroupId],
            ['is_active', true]
        ])->whereNull('blank_option_value_id')->whereHas('layers', function ($query) use ($blankStageId) {
            $query->where('blank_stage_id', $blankStageId);
        })->orderBy('id', 'asc');

        $blankPsds = $blankPsdQuery->get();

        $this->createMockupFileJobs($blankPsds);

        //Print files has no options
        try {
            $printFiles = BlankPrintImage::where([['blank_id', $blankId], ['blank_stage_group_id', $blankStageGroupId], ['is_active', true]])->whereNull('blank_option_value_id')->get();
            $this->createPrintFileJobs($printFiles, $this->productVariantStageFile);
        } catch (Exception $e) {
            $this->logger->error($e);
        }

        //----- Handle print files that are option specific -----//
        if ($this->productVariantStageFile->productVariant->blankVariant->optionValues->count()) {
            $this->logger->debug("Multiple options found");
            $blankVariantOptionValues = [];
            $optionsPrintFiles = [];

            //handle products with at least 1 option mockups
            foreach ($this->productVariantStageFile->productVariant->blankVariant->optionValues as $blankVariantOptionValue) {
                $blankVariantOptionValues[] = $blankVariantOptionValue->id;

                //We need to check for PSDs against all options since BlankPsd isn't created on a blank_variant level but for specific option values
                $blankPsdQuery = BlankPsd::where([
                    ['blank_id', $blankId],
                    ['blank_stage_group_id', $blankStageGroupId],
                    ['is_active', true]
                ])->whereHas('layers', function ($query) use ($blankStageId) {
                    $query->where('blank_stage_id', $blankStageId);
                })->where(function ($query) use ($blankVariantOptionValue) {
                    $query->where('blank_option_value_id', $blankVariantOptionValue->id);
                });

                $blankPsds = $blankPsdQuery->get();
                $this->createMockupFileJobs($blankPsds);
            }

            //handle products with at least 1 option print files
            $parentPrintFiles = BlankPrintImage::where('blank_id', $blankId)
                ->where('blank_stage_group_id', $blankStageGroupId)
                ->where('is_active', true)
                ->whereNull('parent_id')
                ->get();

            foreach ($parentPrintFiles as $parentPrintFile){

                $parentPrintFileOptions = DB::select(DB::raw('
                        SELECT GROUP_CONCAT(blank_option_value_id) as options
                            FROM blank_print_images
                            WHERE blank_id = :blankId
                                AND parent_id = :parentId
                                OR id = :parentPrintFileId
                        '), array(
                            'blankId' => $blankId,
                            'parentId' => $parentPrintFile->id,
                            'parentPrintFileId' => $parentPrintFile->id,
                        ));
                $parentPrintFileOptions = explode(',', $parentPrintFileOptions[0]->options);

                $countMatch = $this->productVariantStageFile->productVariant->blankVariant->optionValues->count();

                foreach ($this->productVariantStageFile->productVariant->blankVariant->optionValues as $blankVariantOptionValue){
                    foreach ($parentPrintFileOptions as $parentPrintFileOption){
                        if($blankVariantOptionValue->id == $parentPrintFileOption){
                            $countMatch--;
                        }
                    }
                }
                if($countMatch == 0){
                    array_push($optionsPrintFiles, $parentPrintFile);
                }
            }

            $this->createPrintFileJobs($optionsPrintFiles, $this->productVariantStageFile);
        }

        $this->productVariantStageFile->status = ProductVariantStageFile::STATUS_FINISHED;
        $this->productVariantStageFile->finished_at = Carbon::now();
        $this->productVariantStageFile->save();

        $this->logger->info("-------------------- End ProcessProductVariantStageFile --------------------");
    }

    /**
     * The job failed to process.
     *
     * @param Exception|Error $exception
     * @return void
     */
    public function failed($exception)
    {
        $this->productVariantStageFile->status = ProductVariantStageFile::STATUS_FAILED;
        $this->productVariantStageFile->save();

        parent::failed($exception);
    }

    public function createMockupFileJobs($blankPsds)
    {
        $this->logger->header('Create Mockup Files');
        // $this->logger->debug('blankPsds:' . json_encode($blankPsds));

//        if (config('app.env') === 'local') {
//            $this->logger->debug("Skipping Mockup Files on local");
//            return;
//        }

        foreach ($blankPsds as $index => $blankPsd) {

            $productMockupFile = ProductMockupFile::where([
                'account_id' => $this->productVariantStageFile->account_id,
                'product_id' => $this->productVariantStageFile->product_id,
                'blank_psd_id' => $blankPsd->id
            ])->first();

            if (!$productMockupFile) {
                $this->logger->debug('No existing ProductMockupFile found, creating one');
                $productMockupFile = ProductMockupFile::create([
                    'account_id' => $this->productVariantStageFile->account_id,
                    'product_id' => $this->productVariantStageFile->product_id,
                    'product_variant_id' => $this->productVariantStageFile->product_variant_id,
                    'blank_psd_id' => $blankPsd->id,
                    'blank_stage_id' => $this->productVariantStageFile->blank_stage_id,
                    'blank_id' => $this->productVariantStageFile->blank_id,
                    'blank_option_value_id' => $blankPsd->blank_option_value_id,
                    'product_art_file_id' => $this->productVariantStageFile->productArtFile->id
                ]);
                $this->logger->debug('Dispatching ProcessProductMockupFile ID ' . $productMockupFile->id);
                if (config('app.env') === 'local') {
                    ProcessProductMockupFile::dispatch($productMockupFile);
                } else {
                    ProcessProductMockupFile::dispatch($productMockupFile)->onQueue('mockup-files');
                }
            } else {
                $this->logger->debug('Existing ProductMockupFile found | ID: ' . $productMockupFile->id);
            }

            $existingMockupFile = ProductVariantMockupFile::where([
                'account_id' => $this->productVariantStageFile->account_id,
                'product_id' => $this->productVariantStageFile->product_id,
                'product_variant_id' => $this->productVariantStageFile->product_variant_id,
                'blank_psd_id' => $blankPsd->id
            ])->first();
            if ($existingMockupFile) {
                $this->logger->debug('Mockup file already exists');
                continue;
            }

            $productVariantMockupFile = ProductVariantMockupFile::create([
                'account_id' => $this->productVariantStageFile->account_id,
                'product_id' => $this->productVariantStageFile->product_id,
                'product_variant_id' => $this->productVariantStageFile->product_variant_id,
                'blank_psd_id' => $blankPsd->id,
                'blank_stage_id' => $this->productVariantStageFile->blank_stage_id,
                'product_variant_stage_file_id' => $this->productVariantStageFile->id,
                'blank_id' => $this->productVariantStageFile->blank_id,
                'blank_option_value_id' => $blankPsd->blank_option_value_id,
                'product_mockup_file_id' => $productMockupFile->id
            ]);
            $this->logger->debug('Created ProductVariantMockupFile ID ' . $productVariantMockupFile->id);
        }
    }
}
