<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Platforms\PlatformStore;
use App\User;
use Carbon\Carbon;
use Illuminate\Foundation\Auth\AuthenticatesUsers;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use SunriseIntegration\Shopify\Helpers\ShopifyHelper;

class LoginController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | Login Controller
    |--------------------------------------------------------------------------
    |
    | This controller handles authenticating users for the application and
    | redirecting them to your home screen. The controller uses a trait
    | to conveniently provide its functionality to your applications.
    |
    */

    use AuthenticatesUsers;

    /**
     * Where to redirect users after login.
     *
     * @var string
     */
//    protected $redirectTo = '/home';

    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware('guest')->except('logout');
        //This was failing too fast
        //$this->middleware('throttle:10,1')->only('login');
    }

    public function authenticate(Request $request)
    {
        $credentials = $request->only('email', 'password');

        if (Auth::attempt($credentials)) {
            return response()->json(['message' => 'Authenticated'], 200);
        }

        return response()->json(['message' => 'Unauthorized'], 401);
    }

    public function login(Request $request)
    {
        $user = User::where('email', $request->email)->first();

        if ($user) {
            if (Hash::check($request->password, $user->password)) {
                $token = Str::random(60);
                $minutes = 60 * 60 * 24 * 90;
                $user->api_token = $user->api_token ?  $user->api_token : $token;
                $user->save();

                //Force Install Sizing Chart (Shopify)
                $accountId = $user->account_id;
                $platformStores = PlatformStore::where('account_id', $accountId)->where('platform_id', 1)->get();
                if($platformStores){
                    foreach ($platformStores as $platformStore){
                        $platformStore->settings()->where('key', 'sizing_chart_script_tag_id')->first()->value ?? ShopifyHelper::setup_sizing_charts_script($platformStore);
                    }
                }

                return response($user->makeVisible('api_token'), 200)->cookie('token', encrypt($user->api_token), $minutes);
            }
        }

        return response(['message' => 'Wrong login information'], 422);
    }

    public function logout(Request $request)
    {
        $user = auth('api')->user();
        $user->api_token = null;
        $user->save();

        return response('Ok', 200)->cookie(Cookie::forget('token'));
    }
}
