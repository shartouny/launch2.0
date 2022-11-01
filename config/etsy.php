<?php

return [
    'name' => 'Etsy',
    'api_key'    => env('ETSY_API_KEY'),
    'api_secret' => env('ETSY_API_SECRET'),
    'temp_dir' => base_path(env('ETSY_TEMP_DIR', 'storage/app/tmpEtsy/'))
];
