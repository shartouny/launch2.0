<?php

namespace App\Models\Platforms;

use App\Scopes\Account;
use Illuminate\Database\Eloquent\Builder;

class PlatformStore extends \SunriseIntegration\TeelaunchModels\Models\Platforms\PlatformStore {
    /**
     * This is a shared model across all Teelaunch Apps
     * Edit the TeelaunchModels Composer Package located at https://github.com/Sunrise-Integration/TeelaunchModels to ensure changes are available across all Teelaunch apps
     */

    protected static function boot()
    {
        parent::boot();
        static::addGlobalScope(new Account());

        static::addGlobalScope('platform', function (Builder $builder) {
            $builder->with('platform');
        });
    }
}
