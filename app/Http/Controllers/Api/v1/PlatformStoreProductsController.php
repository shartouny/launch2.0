<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Platforms\PlatformStoreProductViewCollectionResource;
use App\Http\Resources\Platforms\PlatformStoreProductResource;
use App\Jobs\DeletePlatformProduct;
use App\Jobs\ReSyncPlatformStoreProducts;
use App\Models\Platforms\PlatformStore;
use App\Models\Platforms\PlatformStoreProduct;
use App\Models\Platforms\PlatformStoreProductLog;
use App\Models\Platforms\PlatformStoreProductVariant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\Products\PlatformProductQueue;

/**
 * @group  Platform Products
 *
 * APIs for managing account platform stores
 */

class PlatformStoreProductsController extends Controller
{

    /**
     * Get Platform Products
     *
     * Get platform products
     *
     * @urlParam    platformStoreId required platformStoreId
     * @queryParam  page                     page limit
     */
    public function index(Request $request, $platformStoreId)
    {
        $limit = (int)config('pagination.per_page');

        $platformStoreProducts = PlatformStore::findOrFail($platformStoreId)->products()->search($request->all())->orderBy('created_at', 'desc')->paginate($limit);

        return  PlatformStoreProductViewCollectionResource::collection($platformStoreProducts);
    }

    /**
     * Get Platform Product By Id
     *
     * Get platform product by id
     * @urlParam    platformStoreId required platformStoreId
     * @urlParam    product         required product
     *
     */
    public function show(Request $request, $platformStoreId, $id)
    {
        $platformStoreProduct = PlatformStore::findOrFail($platformStoreId)->products()->findOrFail($id);

        return new PlatformStoreProductResource($platformStoreProduct);
    }

    /**
     * Delete Platform Product
     *
     * Delete platform product
     */
    public function destroy(Request $request, $platformStoreId, $id){
        $ids = explode(',', $id);

        $platformStoreProducts = PlatformStore::findOrFail($platformStoreId)->products()->whereIn('id', $ids)->get();
        foreach ($platformStoreProducts as $platformStoreProduct){
            //Delete all product related sync queues
            $productVariant = $platformStoreProduct->platformStoreProductVariantMapping ? $platformStoreProduct->platformStoreProductVariantMapping->productVariant : null;
            if($productVariant){
                PlatformProductQueue::where('product_id', $productVariant->product->id)->where('platform_store_id', $platformStoreId)->delete();
            }

            $platformStoreProduct->delete();

            PlatformStoreProductLog::create([
                'platform_store_product_id'=> $platformStoreProduct->id,
                'message' => "Product deleted",
                'message_type' => PlatformStoreProductLog::MESSAGE_TYPE_INFO
            ]);

            // Soft Delete PlatformStoreProduct Job
            if(config('app.env') === 'local'){
                DeletePlatformProduct::dispatch($platformStoreProduct);
            } else {
                DeletePlatformProduct::dispatch($platformStoreProduct)->onQueue('deletes');
            }
        }

        return $this->index($request, $platformStoreId);
    }

    public function resync(Request $request, $platformStoreId){

        $platformStore = PlatformStore::findOrFail($platformStoreId);

        if(config('app.env') === 'local'){
            ReSyncPlatformStoreProducts::dispatch($platformStore);
        }
        else {
            ReSyncPlatformStoreProducts::dispatch($platformStore)->onQueue('products');
        }

        return $this->index($request, $platformStoreId);
    }

    /**
     * Ignore Platform Product
     *
     * Ignore platform product
     *
     * @urlParam platformStoreId required platformStoreId
     * @urlParam id              required id
     */
    public function ignore(Request $request, $platformStoreId, $ids)
    {
        $ids = explode(',',$ids);

        $platformStoreProducts = PlatformStore::findOrFail($platformStoreId)->products()->whereIn('id', $ids)->get();
        foreach ($platformStoreProducts as $platformStoreProduct) {
            if (!$platformStoreProduct->is_ignored) {
                $platformStoreProduct->is_ignored = true;
                $platformStoreProduct->save();

                PlatformStoreProductLog::create([
                    'platform_store_product_id'=> $platformStoreProduct->id,
                    'message' => "Product and it's variants ignored",
                    'message_type' => PlatformStoreProductLog::MESSAGE_TYPE_INFO
                ]);
            }


            $platformStoreProductVariants = $platformStoreProduct->variants()->get();
            foreach ($platformStoreProductVariants as $platformStoreProductVariant) {
                $platformStoreProductVariant->is_ignored = true;
                $platformStoreProductVariant->save();
            }
        }

        return $this->responseOk();
    }

    /**
     * Unignore Platform Product
     *
     * Unignore platform product
     *
     * @urlParam platformStoreId required platformStoreId
     * @urlParam id              required id
     */
    public function unignore(Request $request, $platformStoreId, $ids)
    {
        $ids = explode(',',$ids);

        $platformStoreProducts = PlatformStore::findOrFail($platformStoreId)->products()->whereIn('id', $ids)->get();
        foreach ($platformStoreProducts as $platformStoreProduct) {
            $platformStoreProduct->is_ignored = false;
            $platformStoreProduct->save();

            PlatformStoreProductLog::create([
                'platform_store_product_id'=> $platformStoreProduct->id,
                'message' => "Product and it's variants unignored",
                'message_type' => PlatformStoreProductLog::MESSAGE_TYPE_INFO
            ]);

            $platformStoreProductVariants = $platformStoreProduct->variants()->get();
            foreach ($platformStoreProductVariants as $platformStoreProductVariant) {
                $platformStoreProductVariant->is_ignored = false;
                $platformStoreProductVariant->save();
            }
        }

        return $this->responseOk();
    }
}
