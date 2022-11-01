<?php

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

//Obfuscate S3 Image Urls
Route::get('images/{filePath}', 'FileController@showImage')->where('filePath', '.*');

Route::get('password/reset/{token}', 'AppController@index')->name('password.reset');

Route::get('verify', 'AppController@index')->name('verification.notice');
Route::get('auto-login/{hash}', 'AppController@autoLogin');

Route::get('verify/{id}/{hash}', 'AppController@index')->name('verification.verify');
Route::middleware('auth:api')->post('verify/{id}/{hash}', '\App\Http\Controllers\Auth\VerificationController@verify')->name('verification.verify-user');

Route::get('/product/generate-print-files', 'Api\v1\ProductController@generateProductPrintFiles');

Route::get('/sync/order','Api\v1\OrderController@sync');

Route::get('/{any}', 'AppController@index')->where('any', '.*');


