<?php

namespace App\Models\Accounts;

class AccountPayment extends \SunriseIntegration\TeelaunchModels\Models\Accounts\AccountPayment
{
    /**
     * This is a shared model across all Teelaunch Apps
     * Edit the TeelaunchModels Composer Package located at https://github.com/Sunrise-Integration/TeelaunchModels to ensure changes are available across all Teelaunch apps
     */

    protected static function boot()
    {
        parent::boot();
        static::addGlobalScope(new \App\Scopes\Account());
    }

}
