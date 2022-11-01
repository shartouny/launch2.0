<?php


namespace App\Jobs;

use Illuminate\Support\Facades\Log;
use SunriseIntegration\TeelaunchModels\Models\Orders\Order;

class DeleteOrderLineItem extends BaseJob
{
    protected $order;

    /**
     * Create a new job instance.
     *
     * @param Order $order
     */
    public function __construct(Order $order)
    {
        parent::__construct();
        $this->order = $order;
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
        Log::debug("Deleting Order {$this->order->id} Print/Art Files");
        if ($this->order->trashed()) {
            if($this->order->lineItems){
                foreach ($this->order->lineItems as $lineItem){
                    //check the orderlineItem->artFiles is not null
                    Log::debug("Count artFiles: " .count($lineItem->artFiles));
                    if ($lineItem->artFiles) {
                        foreach ($lineItem->artFiles as $artFile) {
                            Log::debug("Deleting artFile: $artFile");

                            if(config('app.env') === 'local'){
                                DeleteOrderLineItemArtFile::dispatch($artFile);
                            } else {
                                DeleteOrderLineItemArtFile::dispatch($artFile)->onQueue('deletes');
                            }
                        }
                    }
                    //check the orderlineItem->printFiles is not null
                    Log::debug("Count printFiles: " .count($lineItem->printFiles));
                    if ($lineItem->printFiles) {
                        foreach ($lineItem->printFiles as $printFile) {
                            Log::debug("Deleting printFile: $printFile");

                            if(config('app.env') === 'local'){
                                DeleteOrderLineItemPrintFile::dispatch($printFile);
                            } else {
                                DeleteOrderLineItemPrintFile::dispatch($printFile)->onQueue('deletes');
                            }
                        }
                    }
                }
            }
        }
    }
}
