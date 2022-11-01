<?php

namespace App\Jobs;

use SunriseIntegration\TeelaunchModels\Models\Products\ProductVariant;
use Illuminate\Support\Facades\Log;

class DeleteProductVariant extends BaseJob
{

    protected $productVariant;

    /**
     * Create a new job instance.
     *
     * @param ProductVariant $productVariant
     */
    public function __construct(ProductVariant $productVariant)
    {
        parent::__construct();
        $this->productVariant = $productVariant;
    }

    /**
     * Execute the job.
     *
     * @return void
     * @throws \Exception
     */
    public function handle()
    {
        foreach ($this->productVariant->stageFiles as $file) {
            Log::debug("Deleting file: $file->file_path");

            if(config('app.env') === 'local'){
                DeleteProductVariantFile::dispatch($file);
            }else {
                DeleteProductVariantFile::dispatch($file)->onQueue('deletes');
            }
        }

        //NOTE: Keep these files as they may be needed for
//        foreach ($this->productVariant->mockupFiles as $file) {
//            //Log::debug("Deleting file: $file->file_path");
//            DeleteProductVariantFile::dispatchNow($file);
//        }
//        foreach ($this->productVariant->printFiles as $file) {
//            //Log::debug("Deleting file: $file->file_path");
//            DeleteProductVariantFile::dispatchNow($file);
//        }

        $this->productVariant->delete();

        //$this->productVariant->forceDelete();
    }
}
