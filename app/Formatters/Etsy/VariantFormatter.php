<?php

namespace App\Formatters\Etsy;

use App\Models\Platforms\PlatformStoreProductVariant;

use SunriseIntegration\Etsy\Models\ListingImage;
use SunriseIntegration\Etsy\Models\ListingProduct;

class VariantFormatter
{
    const PROPERTY_DELIMITER = ' / ';
    const ETSY_PLACEHOLDER_SKU = 'PLACEHOLDER';

    /**
     * @param ListingProduct $platformProduct
     * @return PlatformStoreProductVariant
     */
    static function formatForDb($platformProduct)
    {
        $platformStoreProductVariant = new PlatformStoreProductVariant();
        $platformStoreProductVariant->platform_variant_id = $platformProduct->getProductId();
        $platformStoreProductVariant->data = $platformProduct->toJson();

        $properties = [];
        $propertyValues = $platformProduct->getPropertyValues() ?? [];
        foreach ($propertyValues as $propertyValue) {
            $properties[] = implode(self::PROPERTY_DELIMITER, $propertyValue->values);
        }
        $platformStoreProductVariant->title = implode(self::PROPERTY_DELIMITER, $properties);
        $platformStoreProductVariant->sku = $platformProduct->getSku();

        $offerings = $platformProduct->getOfferings() ?? [];
        $price = $offerings[0] ? ($offerings[0]->price->amount/$offerings[0]->price->divisor) : null;
        $platformStoreProductVariant->price = $price;

        $platformStoreProductVariant->is_ignored = $offerings[0] ? !$offerings[0]->is_enabled : false;
        $platformStoreProductVariant->platform_created_at = null;
        $platformStoreProductVariant->platform_updated_at = null;

        return $platformStoreProductVariant;
    }

}
