<?php

namespace SunriseIntegration\Shopify\Http\Middleware;

use App\Scopes\Account;
use Closure;
use App\Models\Platforms\Platform;
use App\Models\Platforms\PlatformStore;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\View;

class ShopifyAuth
{
    public $platformName = 'Shopify';

    public function handle(Request $request, Closure $next)
    {
        //pass parameters for embedded app
        $shopifyParametersArray = [];
        foreach ($request->query as $name => $value) {
            $shopifyParametersArray[] = "$name=$value";
        }
        $shopifyParameters = '?' . implode('&', $shopifyParametersArray);
        //View::share('shopifyParameters', $shopifyParameters);

        //Get platform and shop
        $platform = Platform::where('name', $this->platformName)->first();

        $method  = $request->method();
        $shopUrl = $request->shop ?? session('shop')->url ?? null;
        if(!is_string($shopUrl) && isset($shopUrl['url'])){
            $shopUrl = $shopUrl['url'];
        }

        $shop = new PlatformStore();
        if ($shopUrl) {
            $shop     = PlatformStore::getByPlatformUrl($platform->id, $shopUrl) ?? new PlatformStore();
        }
        session()->put('platform', $platform);
        session()->put('shop', $shop);
       // View::share('shop', $shop);

        Cookie::queue('shop', $shopUrl, $minutes = 60, config('session.path'), config('session.domain'), $secure = true, $httpOnly = true, false, $sameSite='none');

        return $next($request);
    }

}
