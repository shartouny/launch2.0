<?php


namespace App\Jobs;

use Illuminate\Support\Facades\Log;
use SunriseIntegration\TeelaunchModels\Models\Platforms\PlatformStoreProduct;

class DeletePlatformProduct extends BaseJob
{
    protected $platformStoreProduct;

    /**
     * Create a new job instance.
     *
     * @param PlatformStoreProduct $platformStoreProduct
     */
    public function __construct(PlatformStoreProduct $platformStoreProduct)
    {
        parent::__construct();
        $this->platformStoreProduct = $platformStoreProduct;
    }

    /**
     * Execute the job.
     *
     * @return void
     * @throws \Exception
     */
    public function handle()
    {
        // check the platformStoreProduct->deleted_at is not null
        Log::debug("Deleting Platform Store Product {$this->platformStoreProduct->id} Variants");

        if ($this->platformStoreProduct->deleted_at) {
            if($this->platformStoreProduct->variants){
                Log::debug("Count variants: " .count($this->platformStoreProduct->variants));

                foreach ($this->platformStoreProduct->variants as $platformStoreProductVariant) {
                    if(config('app.env') === 'local'){
                        DeletePlatformProductVariant::dispatch($platformStoreProductVariant);
                    } else {
                        DeletePlatformProductVariant::dispatch($platformStoreProductVariant)->onQueue('deletes');
                    }
                }
            }
        }
    }
}
