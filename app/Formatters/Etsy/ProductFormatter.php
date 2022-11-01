<?php

namespace App\Formatters\Etsy;

use App\Formatters\IFormatter;
use App\Models\Platforms\PlatformStore;
use App\Models\Platforms\PlatformStoreProduct;
use App\Models\Products\Product;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\Log;
use SunriseIntegration\Etsy\Helpers\EtsyHelper;
use SunriseIntegration\Etsy\Models\Listing as EtsyProduct;
use SunriseIntegration\Etsy\Models\Listing;
use SunriseIntegration\Etsy\Models\ListingImage;
use SunriseIntegration\Etsy\Models\ListingProduct;

class ProductFormatter
{
    /**
     * @param Product $product
     * @param $platformStore
     * @param array $options
     * @param Log $logger
     * @return EtsyProduct
     * @throws Exception
     */
    static function formatForPlatform($product, $platformStore, $options, $logger = null)
    {
        $platformProduct = new EtsyProduct();

        try {
            $logger->debug("formatForPlatform options:" . json_encode($options));
            //TODO: Use an object instead of an array for options
            if (!isset($options['shippingTemplateId'])) {
                throw new Exception('Missing required option shippingTemplateId');
            }
            $shippingTemplateId = $options['shippingTemplateId'];

            $blankVariants = $product->variants->pluck('blankVariant');
            $blanks = $blankVariants->pluck('blank')->unique();
//            $logger->debug("blankVariants:" . json_encode($blankVariants));
//            $logger->debug("blanks:" . json_encode($blanks));


            $style = $blanks[0]->category ? $blanks[0]->category->name : null;
            $logger->debug("Blank Metadata: " . json_encode($blanks[0]->metadata));
            $etsyCategoryMetadata = $blanks[0]->metadata()->where('key', 'etsy_category_id')->first();
            $etsySubcategoryMetadata = $blanks[0]->metadata()->where('key', 'etsy_subcategory_id')->first();
            $etsyAttributesMetadata = $blanks[0]->metadata()->where('key', 'etsy_attributes')->first();
            $etsyAttributes = $etsyAttributesMetadata ? json_decode($etsyAttributesMetadata->value) : [];
            $logger->debug("Etsy Attributes: " . json_encode($etsyAttributes));

            $taxonomyId = $etsySubcategoryMetadata->value ?? $etsyCategoryMetadata->value ?? null; //Maybe use Accessories id (1) if no category is defined?
            $logger->debug("Taxonomy ID: $taxonomyId");

            $state ='draft';

            $platformProduct->setState($state);

            $platformProduct->setImageIds([]);

            $platformProduct->setShippingTemplateId($shippingTemplateId);
            $platformProduct->setQuantity(999);
            $platformProduct->setTitle(self::sanitizeTitle($product->name));
            $platformProduct->setDescription(strip_tags($product->description));
            $platformProduct->setPrice($product->variants[0]->price);

            //TODO: This doesn't work
            foreach ($etsyAttributes as $etsyAttributeId => $etsyAttribute) {
                //$logger->info("Check Etsy Attribute | $etsyAttributeId: $etsyAttribute");
                if ($etsyAttributeId == 148789511893) {//Material attr id = 148789511893
                    // $logger->info("FOUND Etsy Attribute Material");
                    $platformProduct->setMaterials([$etsyAttribute]);
                }
            }

            $platformProduct->setTaxonomyId($taxonomyId);
            $platformProduct->setWhoMade('i_did'); //enum(i_did, collective, someone_else)
            $platformProduct->setIsSupply(0);
            $platformProduct->setWhenMade('made_to_order');

            $sanitizedTags = array_map(function ($tag) {
                self::sanitizeTags($tag);
            }, $product->tags);
            $platformProduct->setTags($sanitizedTags);
            $platformProduct->setStyle([$style]); //array(string)
        } catch (Exception $e) {
            $logger->error($e);
        }
        return $platformProduct;
    }

    /**
     * @param Listing $platformProduct
     * @param $platformStore
     * @return PlatformStoreProduct
     */
    //TODO: How to separate the formatting and saving
    static function formatForDb($platformProduct, $platformStore)
    {
        $platformStoreProduct = new PlatformStoreProduct();
        $platformStoreProduct->platform_store_id = $platformStore->id;
        $platformStoreProduct->platform_product_id = $platformProduct->getListingId();
        $platformStoreProduct->data = $platformProduct->toJson();
        $platformStoreProduct->link = $platformProduct->getUrl();
        $platformStoreProduct->image = $platformProduct->getMainimage() ? $platformProduct->getMainimage()->url_75x75 : null;
        $platformStoreProduct->title = html_entity_decode(html_entity_decode($platformProduct->getTitle(), ENT_QUOTES), ENT_QUOTES);
        $platformStoreProduct->platform_created_at = Carbon::createFromTimestamp($platformProduct->getCreationTsz())->toDateTimeString() ?? null;
        $platformStoreProduct->platform_updated_at = Carbon::createFromTimestamp($platformProduct->getLastModifiedTsz())->toDateTimeString() ?? null;

        return $platformStoreProduct;
    }

    /**
     * Removes illegal characters from title
     * @param $title
     * @return mixed
     */
    static function sanitizeTitle($title)
    {
        //Convert all uppercase to ucwords otherwise Etsy complains
        if(strtoupper($title) == $title){
            $title = ucwords(strtolower($title));
        }
        $pattern = "/[^\p{L}\p{Nd}\p{P}\p{Sm}\p{Zs}™©®]/u";
        return substr(self::sanitizeString($title, $pattern), 0, 140);
    }

    static function sanitizeTags($title)
    {
        $pattern = "/[^\p{L}\p{Nd}\p{Zs}\-\'™©®]/u";
        return self::sanitizeString($title, $pattern);
    }

    static function sanitizeString($string, $pattern)
    {
        //If string doesn't match pattern then no changes required
        if (!preg_match($pattern, $string, $matches)) {
            return $string;
        }

        $sanitizedString = $string;
        foreach ($matches as $match) {
            if ($match) {
                $sanitizedString = str_replace($match, '', $sanitizedString);
            }
        }
        return $sanitizedString;
    }
}
