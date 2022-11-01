<?php

Route::prefix('/launch/app')->middleware('web')->group(function () {
    Route::get('/request-install', 'SunriseIntegration\Launch\Http\Controllers\LaunchController@requestInstall')->middleware('auth:api');
    Route::post('/install', 'SunriseIntegration\Launch\Http\Controllers\LaunchController@install');

    Route::get('/{platformStoreId}', 'SunriseIntegration\Launch\Http\Controllers\LaunchController@show');
    Route::put('/{platformStoreId}', 'SunriseIntegration\Launch\Http\Controllers\LaunchController@update');

    Route::prefix('payment')->middleware('api')->group(function(){
        Route::post('/create','SunriseIntegration\Launch\Http\Controllers\LaunchController@createStorePaymentIntent');
        Route::post('/update','SunriseIntegration\Launch\Http\Controllers\LaunchController@updatePaymentMethod');
    });


    Route::prefix('payout')->middleware('api')->group(function(){
        Route::post('/create','SunriseIntegration\Launch\Http\Controllers\LaunchController@createStorePayout');
        Route::post('/confirm','SunriseIntegration\Launch\Http\Controllers\LaunchController@confirmStorePayout');
    });


});


