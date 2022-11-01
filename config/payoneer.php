<?php

return [
  'redirect_uri' => env('PAYONEER_REDIRECT_URI'),
  'client_id' => env('PAYONEER_CLIENT_ID'),
  'client_secret' => env('PAYONEER_CLIENT_SECRET'),
  'partner_id' => env('PAYONEER_PARTNER_ID'),
  'dev' => [
    // Authenticate and request access token in development environment
    'auth_base_url' => env('PAYONEER_DEV_AUTH_URL') . '/api/v2/oauth2/token',
    // Making api calls in development environment
    'api_url' => env('PAYONEER_DEV_API_URL'),
  ],
  'prod' => [
    // Authenticate and request access token in production environment
    'auth_base_url' => env('PAYONEER_PROD_AUTH_URL') . '/api/v2/oauth2/token',
    // Making api calls in production environment
    'api_url' => env('PAYONEER_PROD_API_URL')
  ]
];
