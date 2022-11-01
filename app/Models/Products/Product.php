<?php

namespace App\Models\Products;

use App\Scopes\Account;
use Illuminate\Database\Eloquent\Builder;

class Product extends \SunriseIntegration\TeelaunchModels\Models\Products\Product
{
    /**
     * This is a shared model across all Teelaunch Apps
     * Edit the TeelaunchModels Composer Package located at https://github.com/Sunrise-Integration/TeelaunchModels to ensure changes are available across all Teelaunch apps
     */

    protected static function boot()
    {
        parent::boot();
        static::addGlobalScope(new Account());

        static::addGlobalScope('logs', function (Builder $builder) {
            $builder->with(
                'logs'
            );
        });
    }

    public function useStyleOptions(){
        $blanks = $this->variants->pluck('blankVariant')->pluck('blank')->unique();
        return $blanks->count() > 1;
    }
}
