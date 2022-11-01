<?php

use Illuminate\Support\Facades\Route;

//TODO: For some reason Firefox and Edge prepend the API request with "/api/v1" and Chrome doesn't, need to figure out why that is and then remove these Auth routes
Route::prefix('api')->group(function () {
    Route::post('register', '\App\Http\Controllers\Auth\RegisterController@register');

    Route::middleware('recaptcha')->group(function () {
        Route::post('login', '\App\Http\Controllers\Auth\LoginController@login');

        Route::post('password/reset', '\App\Http\Controllers\Auth\ResetPasswordController@reset');
        Route::post('password/forgot', '\App\Http\Controllers\Auth\ForgotPasswordController@sendResetLinkEmail');

        Route::post('password', '\App\Http\Controllers\Auth\UpdatePasswordController@update');
    });

    Route::post('logout', '\App\Http\Controllers\Auth\LoginController@logout')->middleware('auth:api');
});


Route::middleware('auth:api')->group(function () {
    Route::get('countries', 'CountryController@index');

    Route::post('verify/resend', '\App\Http\Controllers\Auth\VerificationController@resend')->name('verification.resend');
    Route::get('verify/check', '\App\Http\Controllers\Auth\VerificationController@checkIsVerified')->name('verification.check-user');

    Route::get('account-check', 'AccountController@index')->name('account-check');

    Route::middleware('verified')->group(function () {
        // Payoneer
        Route::prefix('payoneer')->group(function () {
            Route::get('initiate-auth', '\App\Http\Controllers\Api\v1\PayoneerApiController@preserve');
        });

        Route::resource('categories', 'BlankCategoryController')->only(['index', 'show']);
        Route::get('categories/{id}/blanks', 'BlankCategoryController@blanks')->name('category-blanks');

        Route::resource('platforms', 'PlatformController')->only(['index', 'show']);

        Route::resource('platforms/{platformId}/stores', 'PlatformStoreController')->only(['index', 'show'])->names([
            'index' => 'platforms.stores.index',
            'show' => 'platforms.stores.show',
        ]);

        Route::resource('stores', 'PlatformStoreController')->only(['index', 'show', 'destroy']);

        Route::resource('stores/{platformStoreId}/products', 'PlatformStoreProductsController')->only(['index', 'show', 'destroy'])->names([
            'index' => 'stores.products.index',
            'show' => 'stores.products.show',
            'destroy' => 'stores.products.destroy'
        ]);

        Route::post('stores/{platformStoreId}/products/resync', 'PlatformStoreProductsController@resync');
        Route::post('stores/{platformStoreId}/products/{id}/ignore', 'PlatformStoreProductsController@ignore');
        Route::post('stores/{platformStoreId}/products/{id}/unignore', 'PlatformStoreProductsController@unignore');

        Route::prefix('stores/{platformStoreId}/products/{platformStoreProductId}/variants')->group(function () {
            Route::post('/{id}/unlink', 'PlatformStoreProductVariantsController@unlink');
            Route::post('/{id}/ignore', 'PlatformStoreProductVariantsController@ignore');
            Route::post('/{id}/unignore', 'PlatformStoreProductVariantsController@unignore');
            Route::delete('/{id}', 'PlatformStoreProductVariantsController@destroy');
        });



        Route::resource('variant-mappings', 'PlatformStoreProductVariantMappingController')->only(['store', 'destroy']);

        Route::resource('blanks', 'BlankController')->only(['index', 'show']);

        Route::resource('account-images', 'AccountImageController')->except(['edit']);

        Route::prefix('products')->group(function () {
            Route::get('/list', 'ProductController@list');
            Route::get('/category/{id}', 'ProductController@getProductsByCategoryId');
            Route::get('/', 'ProductController@index')->name('products');
            Route::get('/platform-products', 'ProductController@getProducts');
            Route::get('/platform-product/{id}', 'ProductController@getProductVariants');
            Route::get('/variants/{id}', 'ProductController@getProductVariants');
            Route::get('/{id}', 'ProductController@show')->name('products.show');
            Route::delete('/{id}', 'ProductController@destroy')->name('products.delete');
            Route::post('/', 'ProductController@store')->name('products.store');
            Route::put('/{id}', 'ProductController@update')->name('products.update');

            Route::post('/{id}/orders-hold', 'ProductController@ordersHold');
            Route::post('/{id}/orders-release', 'ProductController@ordersRelease');
            Route::delete('/{productId}/variants/{variantId}', 'ProductController@destroyVariants');
        });

        Route::resource('variants', 'ProductVariantController')->only(['index', 'show']);

        Route::get('weight-units', 'WeightUnitController@index');

        // Addresses
        Route::prefix('addresses')->group(function () {
            Route::put('/{address}', 'AddressController@update')->name('addresses.update');
        });

        Route::prefix('orders')->group(function () {
            Route::get('/', 'OrderController@index')->name('orders');
            Route::post('/cost', 'OrderController@getOrderCost')->name('order.cost');
            Route::post('/', 'OrderController@store')->name('order.store');
            Route::get('/{id}', 'OrderController@show')->name('order');

            Route::delete('/{id}', 'OrderController@destroy')->name('order.delete');
            Route::post('/{id}/cancel', 'OrderController@cancel');
            Route::post('/{id}/restore', 'OrderController@restore');

            Route::post('/{id}/release', 'OrderController@release');
            Route::post('/{id}/hold', 'OrderController@hold');
            Route::post('/{id}/clear-error', 'OrderController@clearError');
            Route::delete('/{id}/line-items', 'OrderController@deleteLineItems');

            Route::put('/{orderId}/line-items', 'OrderController@updateOrderLineItem');
            Route::post('/{orderId}/line-items/{lineItemId}/print-files', 'OrderLineItemPrintFileController@store');
            Route::get('/{orderId}/line-items/{lineItemId}/print-files', 'OrderLineItemPrintFileController@index');
            Route::delete('/{orderId}/line-items/{lineItemId}/print-files/{printFileId}', 'OrderLineItemPrintFileController@destroy');
            Route::post('/{orderId}/line-items/{lineItemId}/art-files/{artFileId}', 'OrderLineItemArtFileController@store');

            Route::post('/{orderId}/line-items/{lineItemId}/variants', 'OrderController@mapLineItemVariant');
        });

        Route::get('weight-units', 'WeightUnitController@index');

        Route::prefix('stripe')->group(function () {
            Route::post('/authorize', 'StripeApiController@registerIntent')->name('stripe.customer.register');
            Route::post('/save-payment-method', 'StripeApiController@savePaymentMethod')->name('stripe.customer.payment_method');
        });

        Route::prefix('paypal')->group(function () {
            Route::post('/authorize', 'PaypalApiController@authorizePaypal')->name('paypal.authorize');
            Route::post('/save-payment-method', 'PaypalApiController@savePaymentMethod')->name('paypal.save_payment_method');
        });


        Route::prefix('account')->group(function () {
            Route::get('/', 'AccountController@index')->name('account');

            Route::get('/generateToken', 'AccountController@generatePublicApiToken')->name('account.generate-token');
            Route::get('/revokeToken', 'AccountController@revokePublicApiToken')->name('account.revoke-token');

            Route::get('/settings', 'AccountSettingsController@index')->name('account-settings.index');
            Route::post('/settings', 'AccountSettingsController@store')->name('account-settings.store');
            Route::get('/settings/timezones', 'AccountSettingsController@timezones')->name('timezones');
            Route::get('/settings/{id}', 'AccountSettingsController@show')->name('account-settings.show');
            Route::put('/settings/{id}', 'AccountSettingsController@update')->name('account-settings.update');

            Route::get('/addresses', 'AccountController@getAccountAddresses')->name('account.getAddresses');
            Route::post('/address', 'AccountController@storeBillingAddress')->name('account.store-address');
            Route::post('/shipping-label', 'AccountController@storeShippingAddress')->name('account.store-shipping-label');
            Route::post('/packing-slip', 'AccountController@storePackingSlip')->name('account.store-packing-slip');
            Route::post('/packing-slip-logo', 'AccountController@storePackingSlipLogo')->name('account.store-packing-slip-logo');
            Route::post('/change-password', 'AccountController@changePassword');
            Route::post('/change-email', 'AccountController@changeEmail');

            Route::post('/premium-canvas', 'AccountController@storePremiumCanvas')
                ->name('account.store-premium-canvas');
            Route::post('/premium-canvas/{id}', 'AccountController@updatePremiumCanvas')
                ->name('account.update-premium-canvas');

            Route::delete('/account-branding-images/{id}', 'AccountController@destroyAccountBrandingImages')
                ->name('account.destroy-account-branding-images');

            Route::resource('/payment-history', 'AccountPaymentController')->only(['index', 'show']);
            Route::get('/invoices', 'AccountPaymentController@invoices')->name('invoices');
            Route::post('/download', 'AccountPaymentController@downloadPDF')->name('download');

            Route::prefix('/payment-methods')->group(function () {
                Route::get('/', 'AccountPaymentMethodController@index');
                Route::get('/active', 'AccountPaymentMethodController@active');
                Route::get('/{id}', 'AccountPaymentMethodController@show');
                Route::delete('/{id}', 'AccountPaymentMethodController@delete');
            });
        });

        Route::post('/platform-products/all', 'ProcessPlatformProducQueueController@processAll');
        Route::post('/platform-products/{id}', 'ProcessPlatformProducQueueController@process');

        Route::post('/mockup-files/{id}/retrieve', 'ProductVariantMockupFileController@retrieve');
        Route::post('/mockup-files/{id}', 'ProductVariantMockupFileController@process');
        Route::post('/mockup-files', 'ProductVariantMockupFileController@processAllUnfinished');

        Route::post('/print-files/{id}', 'ProductVariantPrintFileController@process');

        if (config('app.env') === 'local') {
            Route::post('slack', function () {
                $slackMessage = new App\Notifications\SlackMessage('Slack test');
                $slackMessage->notify(new App\Notifications\SlackFailedJobNotification());
                return response('test');
            });
        }
    });
});

