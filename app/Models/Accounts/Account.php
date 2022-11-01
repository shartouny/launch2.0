<?php

namespace App\Models\Accounts;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class Account extends \SunriseIntegration\TeelaunchModels\Models\Accounts\Account
{
    /**
     * This is a shared model across all Teelaunch Apps
     * Edit the TeelaunchModels Composer Package located at https://github.com/Sunrise-Integration/TeelaunchModels to ensure changes are available across all Teelaunch apps
     */

    protected static function boot()
    {
        parent::boot();
        static::addGlobalScope('account', function (Builder $builder) {
            if(auth('api')->check() || auth('web')->check()){
                $builder->where('id', Auth::user()->account->id);
            }
        });
    }
}
