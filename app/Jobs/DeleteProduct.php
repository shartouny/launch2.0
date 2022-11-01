<?php

namespace App\Jobs;

use App\Models\Products\Product;

class DeleteProduct extends BaseJob
{

    protected $product;

    /**
     * Create a new job instance.
     *
     * @param Product $product
     */
    public function __construct(Product $product)
    {
        parent::__construct();
        $this->product = $product;
    }

    /**
     * Execute the job.
     *
     * @return void
     * @throws \Exception
     */
    public function handle()
    {
        if ($this->product->deleted_at) {
            foreach ($this->product->variants as $variant) {
                if(config('app.env') === 'local'){
                    DeleteProductVariant::dispatch($variant);
                }else {
                    DeleteProductVariant::dispatch($variant)->onQueue('deletes');
                }
            }
            //$this->product->forceDelete();
        }
    }
}
