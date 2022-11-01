<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Platforms\PlatformCollectionResource;
use App\Http\Resources\Platforms\PlatformResource;
use App\Models\Platforms\Platform;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use SunriseIntegration\TeelaunchModels\Utils\Formatters\SnakeToCamelCase;

/**
 * @group  Platforms
 *
 * APIs for managing account platforms
 */

class PlatformController extends Controller
{
    /**
     * Get Platforms
     *
     * Get account Platform with stores connected to each
     */
    public function index()
    {
        $response = [];
        $platforms = Platform::with(['stores' => function ($query) {
            $query->where('account_id', Auth::user()->account_id);
        }])->where('name', 'not like', '%teelaunch%')
        ->get()
            ->map(function ($platform) {
                foreach ($platform->stores as $store){
                    $store->platformType = $store->platformType;
                }
                return $platform;
            }
        );

        $platforms = $platforms->jsonSerialize();

        foreach ($platforms as $key => $platform){
            foreach ($platform['stores'] as $index => $store){
                if($platform['name'] === 'Rutter'){
                    if(!isset($platform['stores'][ucfirst($store['platformType'])])){
                        $platforms[$key]['stores'][ucfirst($store['platformType'])] = [$platforms[$key]['stores'][$index]];
                    }
                    else{
                        $platforms[$key]['stores'][ucfirst($store['platformType'])][] = $platforms[$key]['stores'][$index];
                    }

                    unset($platforms[$key]['stores'][$index]);
                }
            }
        }

        $response['data'] = SnakeToCamelCase::convertKeysToCamelCase($platforms);

        return $response;
    }

    /**
     * Get Platform
     *
     * Get account Platform by id
     *
     * @urlParam  platform required platform id
     */
    public function show(Request $request, $id)
    {
        $store = Platform::find($id);
        if (!$store) {
            return $this->responseNotFound();
        }

        return new PlatformResource($store);
    }
}
