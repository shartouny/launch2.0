<?php

Route::prefix('/etsy/app')->middleware('web')->group(function () {
    Route::get('/request-install', 'SunriseIntegration\Etsy\Http\Controllers\EtsyController@requestInstall')->middleware('auth:api')->name('etsy.request-install');
    Route::get('/install', 'SunriseIntegration\Etsy\Http\Controllers\EtsyController@install')->name('etsy.install');
});

//Hook receipt
//Route::post('/api/v1/etsy/hooks/', 'SunriseIntegration\Shopify\Http\Controllers\EtsyController@receiveHook')->name('etsy.receive-hook');
