<?php

namespace App\Http\Controllers\Api\v1;

use App\Actions\CreateBatchPlatformProductQueueAction;
use App\Http\Controllers\Controller;
use App\Jobs\ProcessPlatformProductQueue;
use Carbon\Carbon;
use Illuminate\Http\Request;
use SunriseIntegration\TeelaunchModels\Models\Products\PlatformProductQueue;

class ProcessPlatformProducQueueController extends Controller
{
  public function process(Request $request, $id)
  {
    $platformProductQueue = \App\Models\Products\PlatformProductQueue::findOrFail($id);
    $platformProductQueue->status = 1;
    $platformProductQueue->save();

    if(config('app.env') === 'local'){
      ProcessPlatformProductQueue::dispatch($platformProductQueue);
    }else {
      ProcessPlatformProductQueue::dispatch($platformProductQueue)->onQueue('products');
    }

    return response($platformProductQueue->refresh());
  }

  public function processAll(Request $request)
  {
    //Bypass synced products check flag
    $forceSyncProducts = $request->input("forceSyncProducts") ?? false;

    if(!$forceSyncProducts) {
        //Check if the requested products are already synced
        $previouslySyncedProducts = $this->storeSyncedProductsCheck($request);
        if(!empty($previouslySyncedProducts)){
            //Return array of products ids that are already synced and another for products that can be synced
            return response()->json([
                "previouslySynced" => true,
                "previouslySyncedProducts" => $previouslySyncedProducts,
                "productsToSync" => array_values(array_diff($request->selectedProducts, array_keys($previouslySyncedProducts))),
            ]);
        }
    }

    //Create queues for only validated/picked products
    $platformProductQueues = (new CreateBatchPlatformProductQueueAction())(
        $request->input("selectedProducts"),
        $request->input("selectedStores")
    );

    if (!$platformProductQueues) return response("Something went wrong", 500);

    foreach ($platformProductQueues as $platformProductQueue) {
        if (config('app.env') === 'local') {
            ProcessPlatformProductQueue::dispatch($platformProductQueue);
        } else {
            ProcessPlatformProductQueue::dispatch($platformProductQueue)->onQueue('products');
        }
    }

    return $this->responseOk();
  }

  /**
   * Check if selected products are already synced to selected stores
   */
  public function storeSyncedProductsCheck(Request $request)
  {
    return PlatformProductQueue::whereIn('status', [PlatformProductQueue::STATUS_FINISHED, PlatformProductQueue::STATUS_STARTED, PlatformProductQueue::STATUS_PENDING])
        ->whereIn('platform_store_id', $request->selectedStores)
        ->whereIn('product_id', $request->selectedProducts)
        ->select('product_id','platform_store_id')
        ->distinct()
        ->get()
        ->groupBy('product_id')
        ->toArray();
  }
}
