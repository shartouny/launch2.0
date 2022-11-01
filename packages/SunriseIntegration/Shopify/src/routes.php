<?php

Route::prefix('/shopify/app')->middleware('web')->group(function () {

    Route::middleware('hmac', 'shopify')->group(function () {
        //Start page
        Route::get('/', 'SunriseIntegration\Shopify\Http\Controllers\ShopifyController@index')->name('shopify.index');

        //Install route
        Route::get('/install', 'SunriseIntegration\Shopify\Http\Controllers\ShopifyController@install')->name('shopify.install');

        //Login and Register routes
        Route::get('/login', 'SunriseIntegration\Shopify\Http\Controllers\ShopifyController@login')->name('shopify.login');
        Route::get('/register', 'SunriseIntegration\Shopify\Http\Controllers\ShopifyController@register')->name('shopify.register');
        Route::post('/login', 'SunriseIntegration\Shopify\Http\Controllers\ShopifyController@loginAccount')->name('shopify.login-account');
        Route::post('/register', 'SunriseIntegration\Shopify\Http\Controllers\ShopifyController@createAccount')->name('shopify.create-account');

        //HMAC Error
        Route::get('/error', 'SunriseIntegration\Shopify\Http\Controllers\ShopifyController@validationError')->name('shopify.error');
    });
});

// Shopify specific api routes
Route::prefix('/shopify/api')->group(function () {
    // user must be authenticated before calling these routes
    Route::middleware('api', 'sentry')->group(function () {
        Route::post('/associate', 'SunriseIntegration\Shopify\Http\Controllers\ShopifyController@associate')->name('shopify.associate');
    });
    // user wont be authenticated when calling this route
    Route::post('/authenticate', 'SunriseIntegration\Shopify\Http\Controllers\ShopifyController@authenticate')->name('shopify.authenticate');
    Route::get('/request-install', 'SunriseIntegration\Shopify\Http\Controllers\ShopifyController@requestInstall')->name('shopify.request-install');

});

//Hook receipt
Route::post('/api/v1/shopify/hooks/', 'SunriseIntegration\Shopify\Http\Controllers\ShopifyController@receiveHook')->name('shopify.receive-hook');

// Fulfillment
Route::prefix('/api/v1/shopify/fulfillment')->group(function () {
    Route::get('/fetch_tracking_numbers', 'SunriseIntegration\Shopify\Http\Controllers\ShopifyController@fetchTrackingNumbers')->name('shopify.fetch-tracking-numbers');
    Route::get('/fetch_stock', 'SunriseIntegration\Shopify\Http\Controllers\ShopifyController@fetchStock')->name('shopify.fetch-stock');
});

Route::get('/api/v1/shopify/product-sizing-chart/{id}', 'SunriseIntegration\Shopify\Http\Controllers\ShopifyController@fetchProductSizingChart')->name('shopify.fetch-product-sizing-chart');

//testing
Route::get('/test', 'SunriseIntegration\Shopify\Http\Controllers\ShopifyController@test')->name('shopify.test');
