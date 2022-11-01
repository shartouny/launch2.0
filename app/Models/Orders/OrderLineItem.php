<?php

namespace App\Models\Orders;

use App\Scopes\Account;

class OrderLineItem extends \SunriseIntegration\TeelaunchModels\Models\Orders\OrderLineItem {
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
