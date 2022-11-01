<?php

namespace App\Http\Middleware;

use App\User;
use Closure;
use Illuminate\Auth\Middleware\AuthenticateWithBasicAuth;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;
use Laravel\Horizon\Horizon;


class HorizonAuthBasic extends AuthenticateWithBasicAuth
{
    /**
     * @param Request $request
     * @param Closure $next
     * @param null $guard
     * @param null $field
     * @return mixed
     */
    public function handle($request, Closure $next, $guard = null, $field = null)
    {
        Horizon::auth(function (Request $request) {
            if(config('app.env') === 'local'){
                return true;
            }
            $token =$request->cookie('token');
            if (!$token) {
                return false;
            }
            $user = User::select('email')->where('api_token', decrypt($token))->first();
            if (!$user) {
                return false;
            }
            $horizonUsers = explode(',', config('app.horizon_users'));
            return in_array($user->email, $horizonUsers);
        });

        return $next($request);
    }

}
