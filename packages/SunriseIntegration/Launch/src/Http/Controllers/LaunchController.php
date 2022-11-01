<?php

namespace SunriseIntegration\Launch\Http\Controllers;

use App\Models\Orders\PaymentMethod;
use App\Models\Platforms\Platform;
use App\Http\Controllers\Controller;
use App\Models\Platforms\PlatformStoreSettings;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Stripe\StripeClient;

use SunriseIntegration\Launch\LaunchManager;
use SunriseIntegration\Stripe\Stripe;
use SunriseIntegration\TeelaunchModels\Models\Accounts\AccountPaymentMethod;
use SunriseIntegration\TeelaunchModels\Models\Accounts\AccountPaymentMethodMetadata;
use SunriseIntegration\TeelaunchModels\Models\Orders\Address;
use SunriseIntegration\TeelaunchModels\Models\Platforms\PlatformStore;
use SunriseIntegration\TeelaunchModels\Utils\Logger;


use Illuminate\Http\File;
use Illuminate\Http\UploadedFile;

class LaunchController extends Controller
{

    public $platformName = 'Launch';
    public $launch;
    public $shop;
    public $logger;
    public $responseParams;

    public function __construct()
    {

        $this->logger = new Logger('launch');

    }

    function requestInstall(Request $request)
    {
        $this->logger->info("Launch Request Install");
        $registerURL = config('launch.app_url').'/register?r=' . Crypt::encryptString(Auth::user()->account_id) ?? null;

        return response(['registerURL' => $registerURL]);
    }

    function install(Request $request)
    {
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Methods: POST');

        $this->logger->info("Launch Install");

        $storeName = $request->storeName ?? '';
        $fullName = $request->fullName ?? '';
        $email = $request->email ?? '';
        $phoneNumber = $request->phoneNumber ?? '';
        $whiteLabel = $request->whiteLabel ?? false;
        $whiteLabelUrl = $request->whiteLabelUrl ?? '';
        $logo = $request->logo ?? '';
        $favIcon = $request->favIcon ??'';
        $theme = $request->theme;


        if (empty($storeName) || empty($fullName) || empty($email) || empty($phoneNumber)) {
            return response()->json([
                "data" => 'Make sure to fill all required fields.',
                "status" => "error"
            ])->setStatusCode(200);
        }

        $accountId = Crypt::decryptString($request->header('Authorization')) ?? '';
        if (empty($accountId)) {
            return response()->json([
                "data" => 'Request Error, Missing Auth',
                "status" => "error"
            ])->setStatusCode(200);
        }

        $this->logger->info("Account: $accountId");

        // Find or create a platform
        $platform = Platform::firstOrCreate(
            ['name' => $this->platformName],
            [
                'name' => 'launch',
                'manager_class' => LaunchManager::class,
                'logo' => '/images/launch-favicon.svg',
                'enabled' => true
            ]);

        $checkPlatformStore = PlatformStore::where([['name', $storeName]])->withTrashed()->first();
        if ($checkPlatformStore) {
            return response()->json([
                "data" => 'A store with the same name already exist, please pick another name for your store.',
                "status" => "error"
            ])->setStatusCode(200);
        }

        $storeSlug = strtolower(str_replace(' ', '-', $storeName));
        $storeToken = encrypt($storeSlug);


        $baseUrl = config('launch.app_url');
        $platformStore = PlatformStore::where([['account_id', $accountId], ['platform_id', $platform->id], ['url', $baseUrl.'/store/' . $storeSlug]])->withTrashed()->first() ?? new PlatformStore();
        $platformStore->account_id = $accountId;
        $platformStore->name = $storeName;
        $platformStore->url = $baseUrl.'/store/' . $storeSlug;
        $platformStore->platform_id = $platform->id;
        $platformStore->enabled = true;
        $platformStore->deleted_at = null;
        $platformStore->save();

        $platformStore->settings()->updateOrCreate([
            'platform_store_id' => $platformStore->id,
            'key' => 'store_token'
        ], [
            'value' => $storeToken
        ]);

        $platformStore->settings()->updateOrCreate([
            'platform_store_id' => $platformStore->id,
            'key' => 'store_slug'
        ], [
            'value' => $storeSlug
        ]);

        $platformStore->settings()->updateOrCreate([
            'platform_store_id' => $platformStore->id,
            'key' => 'store_name'
        ], [
            'value' => $storeName
        ]);

        $platformStore->settings()->updateOrCreate([
            'platform_store_id' => $platformStore->id,
            'key' => 'full_name'
        ], [
            'value' => $fullName
        ]);

        $platformStore->settings()->updateOrCreate([
            'platform_store_id' => $platformStore->id,
            'key' => 'email'
        ], [
            'value' => $email
        ]);

        $platformStore->settings()->updateOrCreate([
            'platform_store_id' => $platformStore->id,
            'key' => 'phone_number'
        ], [
            'value' => $phoneNumber
        ]);

        $platformStore->settings()->updateOrCreate([
            'platform_store_id' => $platformStore->id,
            'key' => 'white_label'
        ], [
            'value' => $whiteLabel
        ]);

        $platformStore->settings()->updateOrCreate([
            'platform_store_id' => $platformStore->id,
            'key' => 'white_label_url'
        ], [
            'value' => $whiteLabelUrl
        ]);

        $platformStore->settings()->updateOrCreate([
            'platform_store_id' => $platformStore->id,
            'key' => 'status'
        ], [
            'value' => 'incomplete'
        ]);

        $platformStore->settings()->updateOrCreate([
            'key' => 'theme'
        ], [
            'value' => $theme
        ]);

//        if($logo){
//            $contents = file_get_contents($logo);
//            $path = 'launch-store/'.$storeSlug.'/images/'.substr($logo, strrpos($logo, '/') + 1);
//            if (!Storage::disk('s3-nocache')->exists($path)) {
//                Storage::put($path, $contents);
        $platformStore->settings()->updateOrCreate([
            'platform_store_id' => $platformStore->id,
            'key' => 'logo'
        ], [
            'value' => $logo
        ]);
//            }
//        }

//        if($favIcon){
//            $contents = file_get_contents($favIcon);
//            $path = 'launch-store/'.$storeSlug.'/images/'.substr($favIcon, strrpos($favIcon, '/') + 1);
//            if (!Storage::disk('s3-nocache')->exists($path)) {
//                Storage::put($path, $contents);
        $platformStore->settings()->updateOrCreate([
            'platform_store_id' => $platformStore->id,
            'key' => 'fav_icon'
        ], [
            'value' => $favIcon
        ]);
//            }
//        }

        return response()->json([
            'data' => 'Store has been created successfully.',
            'status' => "success",
            'token' => $storeToken,
            'platform_store_id'=> base64_encode($platformStore->id),
        ])->setStatusCode(200);
    }

    function update(Request $request)
    {

        $this->logger->info("Launch update");

        $logo = $request->logo ?? '';
        $favIcon = $request->favIcon ?? '';
        $theme = $request->theme;
        $storeId = $request->platformStoreId;


        $platformStore = PlatformStore::where([['id', $storeId]]);

        $storeSlug = $platformStore->first()->settings->where('key', 'store_slug')->first();

        if ($logo != '') {
            $url = $this->uploadFile($storeSlug, $logo);
            if ($url) {
                $platformStore->first()->settings()
                    ->where(['key' => 'logo'])
                    ->update(['value' => Storage::url($url)]);
            } else {
                return response()->json([
                    "data" => 'Error while uploading Logo',
                    "status" => "success"
                ])->setStatusCode(200);
            }
        }

        if ($favIcon != '') {
            $url = $this->uploadFile($storeSlug, $favIcon);

            if ($url) {
                $platformStore->first()->settings()
                    ->where(['key' => 'fav_icon'])
                    ->update(['value' => Storage::url($url)]);
            } else {
                return response()->json([
                    "data" => 'Error while uploading FavIcon',
                    "status" => "success"
                ])->setStatusCode(200);
            }

        }

        $platformStore->first()->settings()
            ->where(['key' => 'theme'])
            ->update(['value' => $theme]);

        return response()->json([
            "data" => 'Store has been updated successfully',
            "status" => "success"
        ])->setStatusCode(200);
    }

    function updatePaymentMethod(Request $request)
    {

        $stripe = new StripeClient(config('stripe.api_secret'));
        $request->validate(['platform_store_id' => ['required'], 'payment_method' => ['required']]);

        try {
            $stripe_payment_method = $stripe->paymentMethods->retrieve($request->payment_method);
            $platform_store = PlatformStoreSettings::where('platform_store_id', "=", $request->platform_store_id)->where('key', 'subscription_id')->first();
            $platform_store_payment_method = PlatformStoreSettings::where('platform_store_id', "=", $request->platform_store_id)->where('key', 'payment_method_id')->first();
            //the role of this is to keep one single default payment method for a given subscription
            try {
                if($platform_store_payment_method->value)
                    Stripe::detachPaymentMethod($platform_store_payment_method->value);
            }catch (\Exception $e)
            {

            }
            $stripe->subscriptions->update($platform_store->value, ["default_payment_method" => $request->payment_method]);
            $card = $stripe_payment_method->card->toArray()['last4'];
            $exp_month = $stripe_payment_method->card->toArray()['exp_month'];
            $exp_year = $stripe_payment_method->card->toArray()['exp_year'];

            PlatformStoreSettings::updateOrCreate(['platform_store_id' => $request->platform_store_id, 'key' => 'payment_method_id'], ['value' => $request->payment_method]);
            PlatformStoreSettings::updateOrCreate(['platform_store_id' => $request->platform_store_id, 'key' => 'card'], ['value' => $card]);
            PlatformStoreSettings::updateOrCreate(['platform_store_id' => $request->platform_store_id, 'key' => 'exp_month'], ['value' => $exp_month]);
            PlatformStoreSettings::updateOrCreate(['platform_store_id' => $request->platform_store_id, 'key' => 'exp_year'], ['value' => $exp_year]);

            return $this->responseOk(['card' => $card, 'exp_month' => $exp_month, 'exp_year' => $exp_year]);
        } catch (Exception $e) {
            return $this->responseServerError('unable to proceed please contact the customer support');
        }

    }

    function show(Request $request)
    {

        $platformStoreId = $request->platformStoreId;

        return PlatformStoreSettings::where('platform_store_id', $platformStoreId)->get();

    }

    public function createStorePaymentIntent(Request $request)
    {

        $request->validate(['platform_store_id' => ['required']]);
        try {
            $platform_store_customer_id = PlatformStoreSettings::where('platform_store_id', "=", $request->platform_store_id)->where('key', '=', "customer_id")->first();
            return Stripe::createIntent($platform_store_customer_id->value);
        } catch (Exception $e) {
            return $this->responseServerError('unable to proceed please contact the customer support');
        }

    }

    public function createStorePayout(Request $request)
    {

        $request->validate(['platform_store_id' => ['required']]);
        $platform_store_settings_query = PlatformStoreSettings::where([['platform_store_id', $request->platform_store_id]])->whereIn('key', ['email', 'full_name', 'store_name', 'phone_number', 'status'])->get();

        if ($platform_store_settings_query) {
            $platform_store_settings = [];
            foreach ($platform_store_settings_query as $data) {
                $platform_store_settings[$data->key] = $data->value;
            }

            $stripe_connect_account = Stripe::createAccountConnect($platform_store_settings['email']);
            $stripe_connect_link = Stripe::createAccountLink($stripe_connect_account->id, $request->platform_store_id);

            PlatformStoreSettings::updateOrCreate(['platform_store_id' => $request->platform_store_id, 'key' => 'stripe_connect_account'], ['value' => $stripe_connect_account->id]);
            PlatformStoreSettings::updateOrCreate(['platform_store_id' => $request->platform_store_id, 'key' => 'charges_enabled'], ['value' => 'false']);

            return ['account_link' => $stripe_connect_link->url,'platform_store_settings'=>$platform_store_settings];

        }
    }

    public function confirmStorePayout(Request $request)
    {
        $request->validate(['platform_store_id' => ['required']]);
        $platform_store_data = PlatformStoreSettings::where('platform_store_id',$request->platform_store_id)->get();
        $platform_store_settings =[];
        foreach ($platform_store_data as $data) {
            $platform_store_settings[$data->key] = $data->value;
        }

        $platform = PlatformStore::findOrFail($request->platform_store_id);
        //Check if connect payment method exists in database if not create it
        $paymentMethod = PaymentMethod::firstOrCreate([
            'name' => 'Connect'
        ]);

        if($platform_store_settings)
        {
            $connected_account = Stripe::getConnectedAccount($platform_store_settings['stripe_connect_account']);

            if ($connected_account && $connected_account->charges_enabled) {
                $dashboard = Stripe::createDashboardLink($platform_store_settings['stripe_connect_account']);
                $address= Address::create([
                    'first_name'=> $platform_store_settings['full_name'],
                    'last_name'=>'',
                    'address1'=>'',
                    'address2'=>'',
                    'city'=>'',
                    'state'=> '',
                    'zip'=>'',
                    'country'=> $connected_account->country,
                    'phone'=>$platform_store_settings['phone_number']
                ]);
                $account_payment_method =  AccountPaymentMethod::create([
                    'payment_method_id' => $paymentMethod->id,
                    'account_id'=>$platform->account_id,
                    "is_active" => 2, // refers to stripe connect avoiding using it in other payment process
                    'billing_address_id'=> $address->id
                ]);

                AccountPaymentMethodMetadata::create([
                    'key' => 'stripe_connect_id',
                    'value' => $connected_account->id,
                    'account_payment_method_id'=> $account_payment_method->id,
                ]);

                AccountPaymentMethodMetadata::create([
                    'key' => 'platform_store_id',
                    'value' => $request->platform_store_id,
                    'account_payment_method_id'=> $account_payment_method->id,
                ]);

                PlatformStoreSettings::updateOrCreate(['platform_store_id' => $request->platform_store_id, 'key' => 'charges_enabled'], ['value' => "true"]);
                PlatformStoreSettings::updateOrCreate(['platform_store_id' => $request->platform_store_id, 'key' => 'dashboard_url'], ['value' => $dashboard->url]);
                PlatformStoreSettings::updateOrCreate(['platform_store_id' => $request->platform_store_id, 'key' => 'account_payment_method'], ['value' => $account_payment_method->id]);

                return ['success' => 'stripe connect account linked successfully','dashboard_url'=>$dashboard->url];
            }
        }

        return $this->responseServerError('retry');

    }


    static function sanitizeFileName($fileName)
    {
        $name = pathinfo(str_replace(' ', '_', $fileName), PATHINFO_FILENAME);
        $extension = pathinfo($fileName, PATHINFO_EXTENSION);
        $finalName = substr(preg_replace("/[^A-Za-z0-9-_]/", '', $name), 0, 150) . "." . $extension;
        return $finalName;
    }

    function uploadFile($storeSlug, $uploadedFile)
    {
        $fileName = Str::uuid() . '.jpg';
        $fileName = self::sanitizeFileName($fileName);
        $fileDir = 'launch-store/' . $storeSlug->value . '/images';
        $file = $uploadedFile;

        if ($file instanceof File || $file instanceof UploadedFile) {
            $isSuccess = Storage::putFileAs($fileDir, $file, $fileName);
        }

        if (!$isSuccess) {
            Log::error("Failed to save file | $fileName");
            return false;
        }

        Storage::setVisibility("$fileDir/$fileName", 'public');
        return "$fileDir/$fileName";
    }

}
