<?php

namespace App\Http\Controllers\Api\v1;

use App\Jobs\DeleteProductVariant;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\Products\ProductVariant;
use App\Http\Resources\Products\ProductVariantResource;
use App\Http\Resources\Products\ProductVariantCollectionResource;

/**
 * @group  Products
 *
 * APIs for managing products
 */

class ProductVariantController extends Controller
{

    /**
     * Get All Products Variants
     *
     * Get all account products variants
     */
    public function index()
    {
        $productVariants = ProductVariant::get();

        return new ProductVariantCollectionResource($productVariants);
    }

    /**
     * Get Product Variant By Id
     *
     * Get product variant by id
     */
    public function show($id)
    {
        $productVariant = ProductVariant::find($id);
        if (!$productVariant) {
            return $this->responseNotFound();
        }
        return new ProductVariantResource($productVariant);
    }
}
