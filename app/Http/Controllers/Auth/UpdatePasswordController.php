<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class UpdatePasswordController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
        //$this->middleware('throttle:10,1')->only('update');
    }

    public function update(Request $request)
    {
        $request->validate([
            'currentPassword' => ['required', 'string'],
            'newPassword' => ['required', 'string', 'min:8', 'regex:^(?=.*[A-Z])(?=.*[a-z])(?=.*[0-9])(.{8,})', 'confirmed']
        ]);

        if (Hash::check($request->currentPassword, Auth::user()->password) == false) {
            return response(['message' => 'Make sure your password is correct'], 401);
        }

        $token = Str::random(60);
        $minutes = 60 * 60 * 24 * 365;

        $user = Auth::user();
        $user->password = Hash::make($request->newPassword);
        $user->api_token = $token;
        $user->save();

        return response($user, 200)->cookie('token', encrypt($token), $minutes);
    }
}
