<?php

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

Route::post('register', 'Auth\RegisterController@register')->name('register');

// Payoneer
Route::prefix("v1/payoneer")->name("payoneer.")->group(function () {
    Route::get('authenticate', 'Api\v1\PayoneerApiController@authorizePayoneer')->name('authenticate');
    Route::get('preserve/{id}', 'Api\v1\PayoneerApiController@preserve')->name("preserve-id");
});

Route::middleware('recaptcha')->group(function(){
    Route::post('login', 'Auth\LoginController@login')->name('login');
    //Route::post('register', 'Auth\RegisterController@register')->name('register');

    Route::post('password/reset', 'Auth\ResetPasswordController@reset')->name('password.update');
    Route::post('password/forgot', 'Auth\ForgotPasswordController@sendResetLinkEmail');

    Route::post('password', 'Auth\UpdatePasswordController@update')->name('password.update-auth');
});

Route::post('logout', 'Auth\LoginController@logout')->middleware('auth:api')->name('logout');

Route::prefix('v1')->middleware('api', 'sentry')->namespace('Api\v1')->group(base_path('routes/api/v1.php'));


