<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Platforms\PlatformStoreCollectionResource;
use App\Http\Resources\Platforms\PlatformStoreResource;
use App\Models\Platforms\PlatformStore;
use App\Models\Platforms\PlatformStoreSettings;
use Stripe\StripeClient;

use Exception;

use Illuminate\Http\Request;
use SunriseIntegration\Rutter\Http\Api;
use SunriseIntegration\Stripe\Stripe;

/**
 * @group  Stores
 *
 * APIs for managing account platform stores
 */
class PlatformStoreController extends Controller
{

    /**
     * Get Stores
     *
     * Get stores
     */
    public function index()
    {
        $stores = PlatformStore::whereHas('platform', function ($q) {
            $q->where('name', 'not like', '%teelaunch%');
        })->get();

        foreach ($stores as $key => $store) {
            $stores[$key]->platformType = $store->platformType;
        }

        return new PlatformStoreCollectionResource($stores);
    }

    /**
     * Get Store
     *
     * Get store by id
     *
     * @urlParam store required store Id
     */
    public function show(Request $request, $id)
    {
        $store = PlatformStore::find($id);
        if (!$store) {
            return $this->responseNotFound();
        }

        $store->platformType = $store->platformType;

        return new PlatformStoreResource($store);
    }

    /**
     * Get Platform Stores
     *
     * Get platform stores
     */
    public function platformStores(Request $request, $platformId)
    {
        $limit = $request->limit ?? 25;
        $stores = PlatformStore::where('platform_id', $platformId)->paginate($limit);

        return new PlatformStoreCollectionResource($stores);
    }

    /**
     * Get Platform Store
     *
     * Get platform store
     */
    public function platformStore(Request $request, $platformId, $id)
    {
        $store = PlatformStore::where('id', $id)->first();
        if (!$store) {
            return $this->responseNotFound();
        }

        return new PlatformStoreResource($store);
    }

    /**
     * Delete Store
     *
     * Delete store
     *
     * @urlParam store required store Id
     */
    public function destroy(Request $request, $id)
    {
        $store = PlatformStore::find($id);

        //Remove connection from Rutter Api
        if ($store->platform->name === 'Rutter') {
            try {
                $rutter = new Api($store->apiToken);
                $rutter->deleteConnection($store->connectionId);
            } catch (Exception $e) {
                return $this->responseServerError($e);
            }
        }

        //Cancel Launch store monthly subscription
        if ($store->platform->name === 'Launch') {
            try {
                $platform_store_subscription = PlatformStoreSettings::where('platform_store_id', '=', $store->id)->where('key', '=', 'subscription_id')->first();
                $platform_store_status = PlatformStoreSettings::where('platform_store_id', '=', $store->id)->where('key', '=', 'status')->first();
                if ($platform_store_subscription && $platform_store_status && $platform_store_status->value != 'canceled') {
                    $stripe = new StripeClient(config('stripe.api_secret'));
                    $stripe->subscriptions->cancel($platform_store_subscription->value, []);
                    $platform_store_status->update(['value' => 'canceled']);
                }
            } catch (Exception $e) {
                return $this->responseServerError($e);
            }
        }

        //Delete Store
        $store->delete();
        return $this->responseOk();
    }




}
