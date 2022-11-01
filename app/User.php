<?php

namespace App;

use Illuminate\Database\Eloquent\Builder;

class User extends \SunriseIntegration\TeelaunchModels\Models\User
{
    protected static function boot()
    {
        parent::boot();

        self::addGlobalScope('account',function (Builder $builder){
            $builder->with('account');
        });
    }
}
