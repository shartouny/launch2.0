<?php

namespace App\Models\Products;

use App\Scopes\Account;

class ProductVariantPrintFile extends \SunriseIntegration\TeelaunchModels\Models\Products\ProductVariantPrintFile
{
    /**
     * This is a shared model across all Teelaunch Apps
     * Edit the TeelaunchModels Composer Package located at https://github.com/Sunrise-Integration/TeelaunchModels to ensure changes are available across all Teelaunch apps
     */

    protected static function boot()
    {
        parent::boot();
        static::addGlobalScope(new Account());
    }
}
