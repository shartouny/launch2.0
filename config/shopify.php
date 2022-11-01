<?php

return [
    'name' => 'Shopify',
    'api_key'    => env('SHOPIFY_API_KEY'),
    'api_secret' => env('SHOPIFY_API_SECRET'),
    'app_url' => env('SHOPIFY_APP_URL'),
    'tag' => env('SHOPIFY_APP_TAG'),
    'api_version' => env('SHOPIFY_API_VERSION', '2020-07'),
    'fulfillment_service' => env('SHOPIFY_FULFILLMENT_SERVICE', 'teelaunch'),
];
