<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Controllers\Controller;
use App\Jobs\DeletePlatformProductVariant;
use App\Models\Platforms\PlatformStore;
use App\Models\Platforms\PlatformStoreProductLog;
use App\Models\Platforms\PlatformStoreProductVariant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * @group  Platform Products
 *
 * APIs for managing products
 */

class PlatformStoreProductVariantsController extends Controller{

    /**
     * Unlink Platform Product Variants
     *
     * Unlink platform product variants
     * 
     * @urlParam platformStoreId        required platformStoreId
     * @urlParam platformStoreProductId required platformStoreProductId
     * @urlParam id                     required comma separated ids
     */
    public function unlink(Request $request, $platformStoreId, $platformStoreProductVariantId, $ids)
    {
        $accountId = Auth::user()->account_id;
        $ids = explode(',',$ids);
        foreach ($ids as $id) {
            $platformStoreProductVariant = PlatformStoreProductVariant::findOrFail($id);

            if ($accountId !== $platformStoreProductVariant->platformStoreProduct->store->account_id) {
                return $this->responseNotFound();
            }

            if($platformStoreProductVariant->productVariantMapping) {
                if (!$platformStoreProductVariant->productVariantMapping->delete()) {
                    return $this->responseServerError();
                }
                $productVariantName = $platformStoreProductVariant->productVariantMapping->platformStoreProductVariant->title;
                PlatformStoreProductLog::create([
                    'platform_store_product_id'=> $platformStoreProductVariant->platform_store_product_id,
                    'message' => "Product variant $productVariantName unlinked",
                    'message_type' => PlatformStoreProductLog::MESSAGE_TYPE_INFO
                ]);
            }
        }

        return $this->responseOk();
    }

    /**
     * Ignore Platform Product Variants
     *
     * Ignore platform product variants
     * 
     * @urlParam platformStoreId required platformStoreId
     * @urlParam platformStoreProductId required platformStoreProductId
     * @urlParam id required comma separated ids
     * 
     */
    public function ignore(Request $request, $platformStoreId, $platformStoreProductVariantId, $ids)
    {
        $accountId = Auth::user()->account_id;
        $ids = explode(',',$ids);

        $platformStoreProductVariants = PlatformStoreProductVariant::whereIn('id', $ids)->get();

                foreach ($platformStoreProductVariants as $platformStoreProductVariant) {
                    if ($accountId !== $platformStoreProductVariant->platformStoreProduct->store->account_id) {
                        return $this->responseNotFound();
                    }

                    if (!$platformStoreProductVariant->is_ignored){
                        $platformStoreProductVariant->is_ignored = true;
                        $platformStoreProductVariant->save();

                        PlatformStoreProductLog::create([
                            'platform_store_product_id'=> $platformStoreProductVariant->platform_store_product_id,
                            'message' => "Product variant $$platformStoreProductVariant->title ignored",
                            'message_type' => PlatformStoreProductLog::MESSAGE_TYPE_INFO
                        ]);
                    }
                }



        return $this->responseOk();
    }

    /**
     * Unignore Platform Product Variants
     *
     * Unignore platform product variants
     * 
     * @urlParam platformStoreId required platformStoreId
     * @urlParam platformStoreProductId required platformStoreProductId
     * @urlParam id required comma separated ids
     */
    public function unignore(Request $request, $platformStoreId, $platformStoreProductVariantId, $ids)
    {
        $accountId = Auth::user()->account_id;
        $ids = explode(',',$ids);

        $platformStoreProductVariants = PlatformStoreProductVariant::whereIn('id', $ids)->get();

        foreach ($platformStoreProductVariants as $platformStoreProductVariant) {

            if ($accountId !== $platformStoreProductVariant->platformStoreProduct->store->account_id) {
                return $this->responseNotFound();
            }

            if ($platformStoreProductVariant->is_ignored) {
                $platformStoreProductVariant->is_ignored = false;
                $platformStoreProductVariant->save();

                PlatformStoreProductLog::create([
                    'platform_store_product_id'=> $platformStoreProductVariant->platform_store_product_id,
                    'message' => "Product variant $platformStoreProductVariant->title unignored",
                    'message_type' => PlatformStoreProductLog::MESSAGE_TYPE_INFO
                ]);
            }
        }
        return $this->responseOk();
    }

    /**
     * Delete Platform Product Variants
     *
     * Delete platform product variants
     * 
     * @urlParam platformStoreId        required platformStoreId
     * @urlParam platformStoreProductId required platformStoreProductId
     * @urlParam id                     required comma separated ids
     */
    public function destroy(Request $request, $platformStoreId, $platformStoreProductVariantId, $ids)
    {
        $accountId = Auth::user()->account_id;
        $ids = explode(',',$ids);

        $platformStoreProductVariants = PlatformStoreProductVariant::whereIn('id', $ids)->get();

        foreach ($platformStoreProductVariants as $platformStoreProductVariant) {

            if ($accountId !== $platformStoreProductVariant->platformStoreProduct->store->account_id) {
                return $this->responseNotFound();
            }

            $platformStoreProductVariant->delete();

            PlatformStoreProductLog::create([
                'platform_store_product_id'=> $platformStoreProductVariant->platform_store_product_id,
                'message' => "Product variant $platformStoreProductVariant->title deleted",
                'message_type' => PlatformStoreProductLog::MESSAGE_TYPE_INFO
            ]);

            // Soft delete PlatformStoreProductVariant Job
            if(config('app.env') === 'local'){
                DeletePlatformProductVariant::dispatch($platformStoreProductVariant);
            } else {
                DeletePlatformProductVariant::dispatch($platformStoreProductVariant)->onQueue('deletes');
            }
        }

        return $this->responseOk();
    }
}
