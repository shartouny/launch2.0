<?php

namespace App\Jobs;

use App\Models\Orders\OrderLineItem;
use Exception;
use Illuminate\Support\Str;

class ProcessOrderLineItemImage extends BaseJob
{

    /**
     * @var $orderLineItem
     */
    protected $orderLineItem;

    /**
     * @var $imageUrl
     */
    protected $imageUrl;

    /**
     * Create a new job instance.
     *
     * @param $orderLineItem
     * @param $imageUrl
     */
    public function __construct(OrderLineItem $orderLineItem, $imageUrl)
    {
        parent::__construct();
        $this->orderLineItem = $orderLineItem;
        $this->imageUrl = $imageUrl;
    }

    /**
     * Execute the job.
     *
     * @return void
     * @throws Exception
     */
    public function handle()
    {

        //Remove query params from images links
        $this->imageUrl = explode('?', $this->imageUrl)[0];

        $this->orderLineItem->saveFileFromUrl($this->imageUrl,Str::uuid() . '.'. pathinfo($this->imageUrl, PATHINFO_EXTENSION));
    }
}
