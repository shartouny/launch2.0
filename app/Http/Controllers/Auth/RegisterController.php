<?php

namespace App\Http\Controllers\Auth;

use App\Jobs\SendWelcomeEmail;
use App\Models\Accounts\Account;
use App\User;
use App\Http\Controllers\Controller;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Foundation\Auth\RegistersUsers;
use Illuminate\Support\Str;
use Illuminate\Auth\Events\Verified;

class RegisterController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | Register Controller
    |--------------------------------------------------------------------------
    |
    | This controller handles the registration of new users as well as their
    | validation and creation. By default this controller uses a trait to
    | provide this functionality without requiring any additional code.
    |
    */

    use RegistersUsers;

    /**
     * Where to redirect users after registration.
     *
     * @var string
     */
    protected $redirectTo = '/home';

    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware('guest');
    }

    /**
     * Get a validator for an incoming registration request.
     *
     * @param array $data
     * @return \Illuminate\Contracts\Validation\Validator
     */
    protected function validator(array $data)
    {
        return Validator::make($data, [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users'],
            'password' => ['required', 'string', 'min:6', 'confirmed'],
        ]);
    }

    /**
     * @param Request $request
     * @return \Illuminate\Contracts\Routing\ResponseFactory|\Illuminate\Http\Response
     */
    public function register(Request $request)
    {
        if (config('app.allow_registration') == false) {
            return response(['errors' => 'Registration is disabled'], 422);
        }

        $validator = Validator::make($request->all(), [
            'name' => ['string', 'max:100'],
            'email' => ['required', 'string', 'email', 'max:191', 'unique:users'],
            'password' => ['required', 'string', 'min:8', 'max:100', 'regex:/^(?=.*[A-Z])(?=.*[a-z])(?=.*[0-9])(.{8,100})$/', 'confirmed'],
            'store_name' => ['required', 'string', 'max:191'],
            'firstName' => ['required', 'string', 'max:191'],
            'lastName' => ['required', 'string', 'max:191'],
            'phoneNumber' => ['nullable', 'string', 'max:50']
        ]);

        if ($validator->fails()) {
            return response(['errors' => $validator->errors()->all()], 422);
        }

        $code = Str::slug($request->store_name,'-');

        //TODO: Add incrementing integer to code to allow use of same name?
        if(Account::where('code',$code)->first()){
            return $this->responseUnprocessableEntity('Store name already in use, please enter a different name');
        }

        DB::beginTransaction();

        $account = Account::create([
            'name' => $request->store_name,
            'code' => $code
        ]);
        if (!$account) {
            return $this->responseUnprocessableEntity('Registration failed');
        }

        $token = Str::random(60);
        $minutes = 60 * 60 * 24 * 90;

        $user = User::create([
            'name' => $request->name ?? '',
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'first_name' => $request->firstName,
            'last_name' => $request->lastName,
            'phone_number' => $request->phoneNumber ?? null,
            'api_token' => $token,
            'account_id' => $account->id,
            'email_verified_at' => null,
            'hash_code' => $token
        ]);

        if (!$user) {
            DB::rollBack();
            return $this->responseUnprocessableEntity('Registration failed');
        }

        $user->hash_code = md5(Crypt::encryptString($user->id . Str::random(5)));
        $user->save();

        $account->user_id = $user->id;
        $account->save();

        $isShopify = $request->post('isShopify');;

        // automatically verify the user if coming in through shopify
        if($isShopify){
            if ($user->markEmailAsVerified()) {
                event(new Verified($user));
            }

            $userAccount = Account::where('user_id', $user->id)->first();
            if($userAccount){
                $userAccount->email_verified = true;
                $userAccount->save();
            }
        }

        DB::commit();

        if(!$isShopify){
            $user->sendEmailVerificationNotification();
        }

        $user->refresh();

        return response($user->makeVisible('api_token'), 201)->cookie('token', encrypt($token), $minutes);
    }
}
