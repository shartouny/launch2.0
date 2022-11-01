<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Controllers\Controller;

use App\Http\Resources\Platforms\PlatformStoreProductVariantMappingResource;
use App\Models\Platforms\PlatformStoreProductLog;
use App\Models\Platforms\PlatformStoreProductVariant;
use App\Models\Platforms\PlatformStoreProductVariantMapping;
use App\Models\Products\ProductVariant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class PlatformStoreProductVariantMappingController extends Controller
{

    public function store(Request $request)
    {
        $accountId = Auth::user()->account_id;

        $platformStoreProductVariantId = $request->platformStoreProductVariantId;
        $productVariantId = $request->productVariantId;

        $platformStoreProductVariant = PlatformStoreProductVariant::where([['id', $platformStoreProductVariantId]])->with('platformStoreProduct.store')->firstOrFail();
        $productVariant = ProductVariant::where([['id', $productVariantId]])->with('product')->firstOrFail();

        if ($accountId !== $productVariant->product->account_id || $accountId !== $platformStoreProductVariant->platformStoreProduct->store->account_id) {
            return $this->responseBadRequest();
        }

        $platformStoreProductVariantMapping = PlatformStoreProductVariantMapping::where('platform_store_product_variant_id', $platformStoreProductVariant->id)->first() ?? new PlatformStoreProductVariantMapping();
        $platformStoreProductVariantMapping->platform_store_product_variant_id = $platformStoreProductVariant->id;
        $platformStoreProductVariantMapping->product_variant_id = $productVariant->id;
        $platformStoreProductVariantMapping->save();

        $productName= $productVariant->product->name;

        PlatformStoreProductLog::create([
            'platform_store_product_id'=> $platformStoreProductVariant->platform_store_product_id,
            'message' => "Variant $platformStoreProductVariant->title linked to $productName",
            'message_type' => PlatformStoreProductLog::MESSAGE_TYPE_INFO
        ]);

        $mapping = PlatformStoreProductVariantMapping::with('productVariant.blankVariant')->find($platformStoreProductVariantMapping->id);
        return new PlatformStoreProductVariantMappingResource($mapping);
    }

    public function destroy(Request $request, $id)
    {
        $accountId = Auth::user()->account_id;

        $platformStoreProductVariantMapping = PlatformStoreProductVariantMapping::findOrFail($id);
        if ($accountId !== $platformStoreProductVariantMapping->productVariant->product->account_id || $accountId !== $platformStoreProductVariantMapping->platformProductVariant->platformStoreProduct->store->account_id) {
            return $this->responseNotFound();
        }
        if (!$platformStoreProductVariantMapping->delete()) {
            return $this->responseServerError();
        }
        return $this->responseOk();
    }
}
