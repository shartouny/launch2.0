<?php

namespace App\Actions;

use App\Models\Platforms\PlatformStoreProductVariantMapping;
use App\Models\Products\PlatformProductQueue;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;

class CreateBatchPlatformProductQueueAction
{

  public $platform_product_queues = [];
  public $preventDuplicates = true;

  /**
   * @param array $product_ids
   * @param array $store_ids
   *
   * @return Collection
   */
  public function __invoke($product_ids, $store_ids)
  {
    foreach ($product_ids as $product_id) {
      foreach ($store_ids as $store_id) {
        $this->addToPlatformProducctQueues($store_id, $product_id);
      }
    }
    return collect($this->platform_product_queues);
  }

  /**
   * Will only add to queue if there is not a pending or started process
   *
   * @param $store_id
   * @param $product_id
   */
  public function addToPlatformProducctQueues($store_id, $product_id)
  {
    $productQueue = null;

    if ($this->preventDuplicates) {
      $productQueue = PlatformProductQueue::where([
        "account_id" => Auth::user()->account_id,
        "platform_store_id" => $store_id,
        "product_id" => $product_id
      ])->whereBetween('status', [PlatformProductQueue::STATUS_PENDING, PlatformProductQueue::STATUS_STARTED])
        ->where('created_at', '>', Carbon::now()->subMinutes(10))
        ->first();
    }

    if (!$productQueue) {
      $productQueue = PlatformProductQueue::create([
        "account_id" => Auth::user()->account_id,
        "platform_store_id" => $store_id,
        "product_id" => $product_id
      ]);
      if ($productQueue) {
        $this->platform_product_queues[] = $productQueue;
      }
    }
  }
}
