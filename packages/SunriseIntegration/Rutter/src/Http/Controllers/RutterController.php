<?php

namespace SunriseIntegration\Rutter\Http\Controllers;

use App\Formatters\Rutter\ProductFormatter as RutterProductFormatter;
use App\Formatters\Rutter\VariantFormatter as RutterVariantFormatter;
use App\Logger\CronLoggerFactory;
use App\Models\Platforms\Platform;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use SunriseIntegration\Launch\LaunchManager;
use SunriseIntegration\Rutter\Http\Api;
use SunriseIntegration\Rutter\RutterManager;
use SunriseIntegration\TeelaunchModels\Models\Platforms\PlatformStore;
use SunriseIntegration\TeelaunchModels\Models\Platforms\PlatformStoreProduct;
use SunriseIntegration\TeelaunchModels\Models\Platforms\PlatformStoreProductVariant;
use SunriseIntegration\TeelaunchModels\Models\Platforms\PlatformStoreSettings;
use SunriseIntegration\TeelaunchModels\Utils\Logger;

class RutterController extends Controller
{

    public $accessToken;
    public $connectionId;
    public $requestId;
    public $storeUniqueName;
    public $storeUrl;
    public $isReady;
    public $platform;
    public $platformName = 'Rutter';
    public $logger;
    public $responseParams;

    public function __construct(){

        $this->logger = new Logger('rutter');

    }

    public function eventHandler(Request $request)
    {
        Log::debug('Rutter Webhook received', ['request' => $request]);
        switch ($request->type) {
            case 'CONNECTION':
                $this->connectionEventHandler($request);
                break;
            case 'PRODUCT':
                $this->productEventHandler($request);
                break;
        }
        return response('Ok', 200);
    }

    public function connectionEventHandler($request)
    {
        switch ($request->code) {
            case 'INITIAL_UPDATE':
                $platformStoreSettings = PlatformStoreSettings::select('platform_store_id')
                    ->where('key', 'connection_id')
                    ->where('value', $request->connection_id)
                    ->first();
                if ($platformStoreSettings) {
                    $platformStore = PlatformStore::where('id', $platformStoreSettings->platform_store_id)->first();
                    if ($platformStore) {
                        Artisan::call("rutter:import-products-account --account_id=$platformStore->account_id --platform_store_id=$platformStore->id --force=true");
                    }
                }
            break;
        }
        return true;
    }

    public function productEventHandler($request)
    {
        switch ($request->code) {
            case 'PRODUCT_UPDATED':
                if ($request->product) {
                    $updatedProduct = $request->product;
                    $updatedProduct = json_decode(json_encode($updatedProduct));
                    $platformStoreProduct = PlatformStoreProduct::withoutGlobalScopes(['default'])
                        ->where('platform_product_id', $updatedProduct->id)
                        ->first();
                    if ($platformStoreProduct) {
                        $platformStore = PlatformStore::findOrFail($platformStoreProduct->platform_store_id);
                        if ($platformStore) {
                            $this->updateProduct($updatedProduct, $platformStore, $platformStoreProduct);
                        }
                    }
                }
                break;
        }
        return true;
    }

    public function updateProduct($updatedProduct, $platformStore, $platformStoreProduct)
    {
        $platformStoreProductData = RutterProductFormatter::formatForDb($updatedProduct, $platformStore);
        if (empty($platformStoreProduct->image) || (strtotime($platformStoreProduct->platform_updated_at) < strtotime($platformStoreProductData->platform_updated_at))) {
            $this->logger->info("Platform Store Product Exist | ID: $platformStoreProduct->id");
            $platformStoreProduct->image = $platformStoreProductData->image;
            $platformStoreProduct->save();
            $this->logger->info("Platform Store Product Updated | ID: $platformStoreProduct->id");
        }
        foreach ($updatedProduct->variants as $variant) {
            $platformStoreProductVariant = PlatformStoreProductVariant::where('platform_store_product_id', $platformStoreProduct->id)
                ->where(function ($q) use ($variant) {
                    return $q->where('platform_variant_id', $variant->id);
                })->first();
            if($platformStoreProductVariant){
                $platformStoreProductVariantData = RutterVariantFormatter::formatForDb($variant);
                if (empty($platformStoreProductVariant->image) || (strtotime($platformStoreProductVariant->platform_updated_at) < strtotime($platformStoreProductVariantData->platform_updated_at))) {
                    $platformStoreProductVariant->image = $platformStoreProductVariantData->image;
                    $platformStoreProductVariant->save();
                }
            }
        }
    }

    function install(Request $request)
    {

        $this->logger->info("Rutter Store Install");

        $publicToken = $request->public_token ?? '';
        if(empty($publicToken)){
            return response()->json([
                "data" => 'Token is missing, can not proceed with the store installation.',
                "status" => "error"
            ])->setStatusCode(200);
        }

        $accountId = Auth::user()->account_id;
        if(empty($accountId)){
            return response()->json([
                "data" => 'Unauthenticated',
                "status" => "error"
            ])->setStatusCode(200);
        }

        $this->logger->info("Account: $accountId");

        $rutter = new Api();
        $exchangeToken = $rutter->exchangeToken($publicToken);

        if($exchangeToken['success']){
            $this->accessToken = $exchangeToken['data']->access_token;
            $this->connectionId = $exchangeToken['data']->connection_id;
            $this->requestId = $exchangeToken['data']->request_id;
            $this->isReady = $exchangeToken['data']->is_ready;
            $this->platform = ucfirst(strtolower($exchangeToken['data']->platform));

            $rutter = new Api($this->accessToken);
            $storeDetails = $rutter->getStore();
            if($storeDetails['success']){
                $this->storeUniqueName = $storeDetails['data']->store->store_name;
                $this->storeUrl = strpos($storeDetails['data']->store->url, "http") === 0 ? $storeDetails['data']->store->url : 'http://'.$storeDetails['data']->store->url;
            } else{
                return response()->json([
                    "data" => 'An error occurred while trying to fetch your store details, please try again.',
                    "status" => "error",
                    "content" => json_encode($storeDetails)
                ])->setStatusCode(200);
            }

            // Find or create a platform
            $platform = Platform::firstOrCreate(
                [ 'name' => $this->platformName ],
                [
                    'name' => $this->platformName,
                    'manager_class' => RutterManager::class,
                    'logo' => '/images/'.strtolower($this->platformName).'-favicon.svg',
                    'enabled' => true
                ]);

            $platformStore = PlatformStore::where([['account_id', $accountId], ['platform_id', $platform->id], ['name', $this->storeUniqueName]])->withTrashed()->first() ?? new PlatformStore();
            $platformStore->account_id = $accountId;
            $platformStore->platform_id = $platform->id;
            $platformStore->enabled = true;
            $platformStore->deleted_at = null;
            $platformStore->name = $this->storeUniqueName ?? '';
            $platformStore->url = $this->storeUrl ?? '';

            $platformStore->save();

            $platformStore->settings()->updateOrCreate([
                'platform_store_id' => $platformStore->id,
                'key' => 'api_token'
            ], [
                'value' => encrypt($this->accessToken)
            ]);

            $platformStore->settings()->updateOrCreate([
                'platform_store_id' => $platformStore->id,
                'key' => 'connection_id'
            ], [
                'value' => $this->connectionId
            ]);

            $platformStore->settings()->updateOrCreate([
                'platform_store_id' => $platformStore->id,
                'key' => 'request_id'
            ], [
                'value' => $this->requestId
            ]);

            $platformStore->settings()->updateOrCreate([
                'platform_store_id' => $platformStore->id,
                'key' => 'store_unique_name'
            ], [
                'value' => $this->storeUniqueName
            ]);

            $platformStore->settings()->updateOrCreate([
                'platform_store_id' => $platformStore->id,
                'key' => 'is_ready'
            ], [
                'value' => $this->isReady
            ]);

            $platformStore->settings()->updateOrCreate([
                'platform_store_id' => $platformStore->id,
                'key' => 'platform'
            ], [
                'value' => str_replace('_', '', $this->platform)
            ]);

            Artisan::call("rutter:import-products-account --account_id=$accountId --platform_store_id=$platformStore->id");

            Artisan::call("rutter:import-orders-account --account_id=$accountId --platform_store_id=$platformStore->id");

            return response()->json([
                "data" => 'Store has been created successfully.',
                "status" => "success"
            ])->setStatusCode(200);
        }

        return response()->json([
            "data" => 'Something went wrong while trying to fetch your store details, please try again',
            "status" => "error",
            "content" => json_encode($exchangeToken)
        ])->setStatusCode(200);

    }

}
