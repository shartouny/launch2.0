<?php

namespace SunriseIntegration\Shopify\Helpers;

use SunriseIntegration\Shopify\API;
use SunriseIntegration\TeelaunchModels\Utils\Logger;
use App\Models\Platforms\PlatformStoreSettings;
use Illuminate\Support\Facades\DB;
use App\Models\Platforms\PlatformStoreProductVariantMapping;

class ShopifyHelper {

    public static $storePassword = '';
    public static $generatedSignature = '';

    public static function generateSignature($query_params)
    {
        $userPassword = self::$storePassword;
        $generatedSignature = '';

        unset($query_params['utf8'], $query_params['authenticity_token'], $query_params['x_signature']);

        foreach ($query_params as $key => $val) {
            $params[] = "$key$val";
        }
        sort($params);
        $finalParms = implode('', $params);

        $generatedSignature = hash_hmac('sha256', $finalParms, $userPassword);
        self::$generatedSignature = $generatedSignature;

        return $generatedSignature;
    }


    public static function getCheckoutToken($query_params)
    {
        $token = '';

        if ($query_params['x_url_complete']){

            // The Shopify checkout token is in the URL
            $urlParts = explode('/', $query_params['x_url_complete']);

            if (!empty($urlParts[5])) {
                $token = $urlParts[5];
            }
        }

        return $token;
    }


    /**
     * @param string $x_signature
     * @return boolean
     */
    public static function validateSignature($x_signature){

        $validated = false;

        if (!empty($x_signature) && !empty(self::$generatedSignature)) {
            if (trim($x_signature) == trim(self::$generatedSignature)) {
                $validated = true;
            }
        }

        return $validated;
    }


    public static function get_client_ip_env() {

        $ipaddress = '';

        if (getenv('HTTP_CLIENT_IP'))
            $ipaddress = getenv('HTTP_CLIENT_IP');
        else if(getenv('HTTP_X_FORWARDED_FOR'))
            $ipaddress = getenv('HTTP_X_FORWARDED_FOR');
        else if(getenv('HTTP_X_FORWARDED'))
            $ipaddress = getenv('HTTP_X_FORWARDED');
        else if(getenv('HTTP_FORWARDED_FOR'))
            $ipaddress = getenv('HTTP_FORWARDED_FOR');
        else if(getenv('HTTP_FORWARDED'))
            $ipaddress = getenv('HTTP_FORWARDED');
        else if(getenv('REMOTE_ADDR'))
            $ipaddress = getenv('REMOTE_ADDR');
        else
            $ipaddress = 'UNKNOWN';

        return $ipaddress;
    }

    public static function getStoreUrlBase($platformStore) {
        $url = $platformStore->url;
        if($url) {
            return strpos($url,'http://') === 0 || strpos($url,'https://') === 0 ? $url : 'https://'.$url;
        } else {
            return null;
        }
    }

    public static function get_sizing_charts_script(){
        $app_url = config('app.url');
        return [
            "script_tag"=> [
                "event" => "onload",
                "src"=> "{$app_url}/sizing-charts-script.js"
            ]
        ];
    }

    public static function get_fulfillment_service_creation_data() {
        return [
            "name" => config('shopify.fulfillment_service'),
            "handle" => config('shopify.fulfillment_service'),
            "callback_url" => config('app.url') . '/api/v1/shopify/fulfillment',
            "inventory_management" => false,
            "tracking_support" => true,
            "requires_shipping_method" => true,
            "format" => 'json'
        ];
    }

    public static function setup_sizing_charts_script($platformStore, $logger=null){
        if($logger == null) {
            $logger = new Logger('shopify');
        }
        $apiOptions = [
            'shop' => $platformStore->url,
            'key' => config('shopify.api_key'),
            'secret' => config('shopify.api_secret'),
            'token' => $platformStore->api_token,
            'logger' => $logger
        ];
        $shopifyApi = new API($apiOptions);

        $response = $shopifyApi->addSizingChartsScript(ShopifyHelper::get_sizing_charts_script());

        if ($shopifyApi->lastHttpCode !== 201 || !isset($response->script_tag)) {
            $logger->error("Failed to create sizing chart script tag");
            $logger->error("Response: " . json_encode($response));
            return null;
        }

        if($shopifyApi->lastHttpCode() === 201) {
            $platformStore->settings()->updateOrCreate([
                'key' => 'sizing_chart_script_tag_id'
            ], [
                'value' => $response->script_tag->id
            ]);
        }
        $logger->info("Attach sizing chart script tag: " . json_encode($response));

    }

    public static function setup_fulfillment_service($platformStore, $logger=null) {
        if($logger == null) {
            $logger = new Logger('shopify');
        }
        $apiOptions = [
            'shop' => $platformStore->url,
            'key' => config('shopify.api_key'),
            'secret' => config('shopify.api_secret'),
            'token' => $platformStore->api_token,
            'logger' => $logger
        ];
        $shopifyApi = new API($apiOptions);
        $retry = 0;
        do {
            $response = $shopifyApi->createFulfillmentService(ShopifyHelper::get_fulfillment_service_creation_data());
            $retry++;
            $logger->debug("RESPONSE " . json_encode($response, JSON_PRETTY_PRINT));
            $logger->debug("LAST CODE " . $shopifyApi->lastHttpCode);
            // shopify returns 201 here for success
            // 422 already set with same name
            $logger->debug("Retry " . $retry);
        } while ($retry < 3 && $shopifyApi->lastHttpCode !== 201);

        if ($shopifyApi->lastHttpCode !== 201 || !isset($response->fulfillment_service)) {
            //Failed after 3 attempts
            $logger->error("Failed to set fulfillment service");
            $logger->error("Response: " . json_encode($response));
            return null;
        }
        if($shopifyApi->lastHttpCode() === 201) {
            $platformStore->settings()->updateOrCreate([
                'key' => 'fulfillment_service_location_id'
            ], [
                'value' => $response->fulfillment_service->location_id
            ]);

            $platformStore->settings()->updateOrCreate([
                'key' => 'fulfillment_service_id'
            ], [
                'value' => $response->fulfillment_service->id
            ]);
        }
        $logger->info("Set Fulfillment Service Response: " . json_encode($response));
    }

    public static function getBrokenVariantLinks($page=0){
        /*
        SELECT
        pspv.sku as pspv_sku,
        bv.sku as bv_sku
        FROM
        platform_store_product_variants AS pspv
        INNER JOIN platform_store_product_variant_mappings pspvm on pspv.id = pspvm.platform_store_product_variant_id
        INNER JOIN platform_store_products psp on pspv.platform_store_product_id = psp.id
        INNER JOIN platform_stores ps on psp.platform_store_id = ps.id
        INNER JOIN platforms p on ps.platform_id = p.id
        INNER JOIN product_variants pv on pspvm.product_variant_id = pv.id
        INNER JOIN blank_variants bv on pv.blank_variant_id = bv.id
        WHERE
            pspv.sku != bv.sku
        AND
            p.name = "Shopify"
        */

        $brokenVariantLinks =  DB::table('platform_store_product_variants', 'pspv')
            ->join('platform_store_product_variant_mappings AS pspvm', 'pspv.id', '=', 'pspvm.platform_store_product_variant_id')
            ->join('platform_store_products AS psp', 'pspv.platform_store_product_id', '=', 'psp.id')
            ->join('platform_stores AS ps', 'psp.platform_store_id', '=', 'ps.id')
            ->join('platforms AS p', 'ps.platform_id', '=', 'p.id')
            ->join('product_variants AS pv', 'pspvm.product_variant_id', '=', 'pv.id')
            ->join('blank_variants AS bv', 'pv.blank_variant_id', '=', 'bv.id')
            ->select(
                'pv.product_id AS product_id',
                'pspv.id AS pspv_id',
                'pspv.sku AS pspv_sku',
                'bv.sku AS bv_sku',
                'pspv.title as pspv_title',
                'pspvm.id AS pspvm_id',
                'pv.id AS pv_id',
                'ps.id AS platform_store_id'
            )
            ->whereRaw('pspv.sku != bv.sku')
            ->whereRaw('p.name = "Shopify"')
            ->simplePaginate(
                100,
                ['*'],
                'page',
                $page
            );
        return $brokenVariantLinks;
    }

    public static function fixBrokenVariantLinks($brokenVariantLinks){
        $matches = [];
        foreach($brokenVariantLinks as $brokenVariant){
            $platformStoreProductVariantMapping = PlatformStoreProductVariantMapping::where('id', $brokenVariant->pspvm_id)->first();
            // see if we can find a real id
            $targetVariantLinks =  DB::table('product_variants', 'tpv')
            ->join('products AS tp', 'tpv.product_id', '=', 'tp.id')
            ->join('blank_variants AS tbv', 'tpv.blank_variant_id', '=', 'tbv.id')
            ->join('blanks AS tb', 'tbv.blank_id', '=', 'tb.id')
            ->select(
                'tpv.id AS product_variant_id',
                'tp.id AS product_id',
                'tbv.sku AS product_variant_sku'
            )
            ->where('tp.id', '=', $brokenVariant->product_id)
            ->where('tbv.sku', '=', $brokenVariant->pspv_sku)
            ->first();
            if($targetVariantLinks){
                // we found a different target.
                $platformStoreProductVariantMapping->product_variant_id = $targetVariantLinks->product_variant_id;
                $platformStoreProductVariantMapping->save();
            }
            $brokenVariant->matches = $targetVariantLinks;
            $matches[] = $brokenVariant;
        }
        return $matches;
    }

    public static function fixBrokenVariantLinks2($brokenVariantLinks){
        $matches = [];
        foreach($brokenVariantLinks as $brokenVariant){
            if(str_starts_with($brokenVariant->pspv_sku, 'BC3001CVC') && count(explode('-', $brokenVariant->pspv_sku)) == 2 ) {
                // unique case where sku was not correct on product creation Missing size
                // get size from title 
                $sizeArr = explode(' / ', $brokenVariant->pspv_title);
                if(count($sizeArr) != 2) {
                    continue;
                }

            } else {
                continue;
            }

            // create new sku
            $size = $sizeArr[0];

            $targetSku =  $brokenVariant->pspv_sku . '-' . $size;
            $platformStoreProductVariantMapping = PlatformStoreProductVariantMapping::where('id', $brokenVariant->pspvm_id)->first();
            // see if we can find a real id
            $targetVariantLinks =  DB::table('product_variants', 'tpv')
            ->join('products AS tp', 'tpv.product_id', '=', 'tp.id')
            ->join('blank_variants AS tbv', 'tpv.blank_variant_id', '=', 'tbv.id')
            ->join('blanks AS tb', 'tbv.blank_id', '=', 'tb.id')
            ->select(
                'tpv.id AS product_variant_id',
                'tp.id AS product_id',
                'tbv.sku AS product_variant_sku'
            )
            ->where('tp.id', '=', $brokenVariant->product_id)
            // try both sizes
            ->whereIn('tbv.sku', [$brokenVariant->pspv_sku . '-' . $sizeArr[0], $brokenVariant->pspv_sku . '-' . $sizeArr[1]])
            // ->where('tbv.sku', '=', $targetSku)
            ->first();
            if($targetVariantLinks){
                // we found a different target.
                $platformStoreProductVariantMapping->product_variant_id = $targetVariantLinks->product_variant_id;
                $platformStoreProductVariantMapping->save();
            }
            $brokenVariant->matches = $targetVariantLinks;
            $matches[] = $brokenVariant;
        }
        return $matches;
    }

}
