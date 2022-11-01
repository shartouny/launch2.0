<?php

Route::prefix('/rutter/app')->middleware('web')->group(function () {
    Route::get('/install', 'SunriseIntegration\Rutter\Http\Controllers\RutterController@install')->middleware('auth:api');
});
Route::post('/api/v1/rutter/hooks/', 'SunriseIntegration\Rutter\Http\Controllers\RutterController@eventHandler')->name('rutter.event-handler');
