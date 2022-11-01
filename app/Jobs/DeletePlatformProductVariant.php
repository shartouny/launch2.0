<?php


namespace App\Jobs;

use SunriseIntegration\TeelaunchModels\Models\Platforms\PlatformStoreProductVariant;
use Illuminate\Support\Facades\Log;

class DeletePlatformProductVariant extends BaseJob
{
    protected $platformStoreProductVariant;

    /**
     * Create a new job instance.
     *
     * @param PlatformStoreProductVariant $platformStoreProductVariant
     */
    public function __construct(PlatformStoreProductVariant $platformStoreProductVariant)
    {
        parent::__construct();
        $this->platformStoreProductVariant = $platformStoreProductVariant;
    }

    /**
     * Execute the job.
     *
     * @return void
     * @throws \Exception
     */
    public function handle()
    {

        if($this->platformStoreProductVariant->platformStoreProductVariantMapping){
            Log::debug("Count variants stageFiles: " .count($this->platformStoreProductVariant->platformStoreProductVariantMapping->productVariant->stageFiles));

            foreach ($this->platformStoreProductVariant->platformStoreProductVariantMapping->productVariant->stageFiles as $file) {
                Log::debug("Deleting stageFile: $file->file_path");

                if(config('app.env') === 'local'){
                    DeletePlatformProductVariantFile::dispatch($file);
                }else {
                    DeletePlatformProductVariantFile::dispatch($file)->onQueue('deletes');
                }
            }
        }

        Log::debug("Deleting Variant: {$this->platformStoreProductVariant->id}");
        $this->platformStoreProductVariant->delete();
    }
}
