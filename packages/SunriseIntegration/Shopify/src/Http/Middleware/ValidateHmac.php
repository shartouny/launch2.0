<?php

namespace SunriseIntegration\Shopify\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Contracts\View\Factory as ViewFactory;
use Illuminate\Support\Facades\Route;

class ValidateHmac
{
    protected $view;

    public function __construct(ViewFactory $view)
    {
        $this->view = $view;
        $this->view->share('shopifyParameters', null);
    }

    public function handle(Request $request, Closure $next)
    {
        if (!$request->wantsJson() && $request->has('shop') && !$this->validateHmac($request)) {
            return redirect()->route('shopify.error');
        }

        return $next($request);
    }

    public function validateHmac(Request $request)
    {
        $hmac = $request->input('hmac');
        $key  = config('shopify.api_secret');

        $dataArray = [];
        foreach ($request->query as $name => $value) {
            if ($name !== 'hmac') {
                $dataArray[] = "$name=$value";
            }
        }
        $data = implode('&', $dataArray);

        if ($hmac === hash_hmac('sha256', $data, $key)) {
            return true;
        }

        Log::warning("HMAC failed", ['HMAC' => $hmac, 'data' => $data]);

        return false;
    }

}
