<?php

namespace App\Http\Controllers;

use App\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;

class AppController extends Controller
{
    public function index()
    {
        return view('app');
    }

    public function autoLogin($hash, Request $request)
    {
        $user = User::where('hash_code', $hash)->first();

        if ($user) {
            $token = Str::random(60);
            $newHash = md5(Crypt::encryptString($user->id . Str::random(5)));
            $user->api_token = !empty($user->api_token) ?  $user->api_token : $token;
            $user->hash_code = $newHash;

            $user->save();

            $user->makeVisible('api_token');

            return redirect('/login?token='.$user->api_token);
        }

        return response(['message' => 'Wrong login information'], 422);
    }
}
