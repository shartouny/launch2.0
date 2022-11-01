<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Accounts\AccountCollectionResource;
use App\Http\Resources\Accounts\AccountResource;
use App\Models\Accounts\Account;
use App\Models\Accounts\AccountBrandingImage;
use App\Models\Accounts\AccountBrandingImageType;
use App\Models\Accounts\AccountShippingLabel;
use App\Models\ImageType;
use App\Models\Orders\Address;
use App\Models\Accounts\AccountSettings;
use App\Models\Orders\Order;
use App\User;
use DateTimeZone;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

/**
 * @group  Account
 *
 * APIs for managing account
 */

class AccountController extends Controller
{
    /**
     * Get Info
     *
     * Get account information
     */
    public function index(Request $request)
    {
        $account = Account::with('user', 'shippingLabel', 'shippingLabel.shippingAddress', 'shippingLabel.billingAddress', 'brandingImages', 'brandingImages.brandingImageType', 'brandingImages.imageType')->first();

        return new AccountResource($account);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param Request $request
     * @return AccountResource|Response
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param Request $request
     * @param int $id
     * @return AccountResource
     */
    public function update(Request $request, $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param int $id
     */
    public function destroy($id)
    {
        //
    }

    /**
     * Store Billing Address
     *
     * Store account billing address details
     * 
     * @bodyParam  accountInfo.company  string required company
     * @bodyParam  accountInfo.address1  string required address1
     * @bodyParam  accountInfo.address2  string  address2
     * @bodyParam  accountInfo.city  string required city
     * @bodyParam  accountInfo.state  string  state
     * @bodyParam  accountInfo.zip  string required zip
     * @bodyParam  accountInfo.country  string required country
     * @bodyParam  accountInfo.firstName  string required firstName
     * @bodyParam  accountInfo.lastName  string required lastName
     * @bodyParam  accountInfo.phoneNumber  string  phoneNumber
     * @bodyParam  accountInfo.vat  string  vat
     * 
     */
    public function storeBillingAddress(Request $request)
    {
        $request->validate([
            'accountInfo.company' => 'required|string|max:190',
            'accountInfo.address1' => 'required|string|max:190',
            'accountInfo.address2' => 'string|max:190|nullable',
            'accountInfo.city' => 'required|string|max:190',
            'accountInfo.state' => 'nullable|string|max:190',
            'accountInfo.zip' => 'required|string|max:190',
            'accountInfo.country' => 'required|string|max:190',
            'accountInfo.firstName' => 'required|string|max:190',
            'accountInfo.lastName' => 'required|string|max:190',
            'accountInfo.phoneNumber' => 'nullable|string|max:50',
            'accountInfo.vat' => 'nullable|string|max:100'
        ]);

        $account = Account::with('user', 'shippingLabel')->where('id', Auth::user()->account_id)->first();

        $userInfo = $account->user;

        $shippingLabel = $account->shippingLabel;
        if (!$shippingLabel) {
            $shippingLabel = new AccountShippingLabel();
            $account->shippingLabel()->save($shippingLabel);
            $shippingLabel->refresh();
        }

        $billingAddress = $shippingLabel->billingAddress;
        if (!$billingAddress) {
            $billingAddress = new Address();
            $shippingLabel->billingAddress()->save($billingAddress);
            $shippingLabel->billing_address_id = $billingAddress->id;
            $shippingLabel->save();
            $billingAddress->refresh();
        }

        $shippingLabel->vat = $request->accountInfo['vat'] ?? null;
        $billingAddress->company = $request->accountInfo['company'];
        $billingAddress->address1 = $request->accountInfo['address1'];
        $billingAddress->address2 = $request->accountInfo['address2'] ?? null;
        $billingAddress->city = $request->accountInfo['city'];
        $billingAddress->state = $request->accountInfo['state'] ?? null;
        $billingAddress->zip = $request->accountInfo['zip'];
        $billingAddress->country = $request->accountInfo['country'];
        $userInfo->first_name = $request->accountInfo['firstName'];
        $userInfo->last_name = $request->accountInfo['lastName'];
        $userInfo->phone_number = $request->accountInfo['phoneNumber'] ?? null;

        $shippingLabel->save();
        $billingAddress->save();
        $userInfo->save();

        return $this->index($request);
    }

    /**
     * Store Shipping Address
     *
     * Store account shipping address details
     */
    public function storeShippingAddress(Request $request)
    {
        $request->validate([
            'shippingLabel.company' => 'required|string|max:190',
            'shippingLabel.address1' => 'required|string|max:190',
            'shippingLabel.address2' => 'string|max:190|nullable',
            'shippingLabel.city' => 'required|string|max:190',
            'shippingLabel.state' => 'nullable|string|max:190',
            'shippingLabel.zip' => 'required|string|max:190',
            'shippingLabel.country' => 'required|string|max:190',
        ]);

        $account = Account::where('id', Auth::user()->account_id)->first();
        $shippingLabel = $account->shippingLabel;
        if (!$shippingLabel) {
            $shippingLabel = new AccountShippingLabel();
            $account->shippingLabel()->save($shippingLabel);
            $shippingLabel->refresh();
        }

        $shippingAddress = $shippingLabel->shippingAddress;
        if (!$shippingAddress) {
            Log::info("No shipping address");
            $shippingAddress = new Address();
            $shippingAddress->company = $request->shippingLabel['company'];
            $shippingAddress->address1 = $request->shippingLabel['address1'];
            $shippingAddress->address2 = $request->shippingLabel['address2'] ?? null;
            $shippingAddress->city = $request->shippingLabel['city'];
            $shippingAddress->state = $request->shippingLabel['state'] ?? null;
            $shippingAddress->zip = $request->shippingLabel['zip'];
            $shippingAddress->country = $request->shippingLabel['country'];
            $shippingLabel->shippingAddress()->save($shippingAddress);

            $shippingLabel->shipping_address_id = $shippingAddress->id;
            $shippingLabel->save();

            Log::info("Shipping Address Created:".json_encode($shippingAddress));
        } else {
            $shippingAddress->company = $request->shippingLabel['company'];
            $shippingAddress->address1 = $request->shippingLabel['address1'];
            $shippingAddress->address2 = $request->shippingLabel['address2'] ?? null;
            $shippingAddress->city = $request->shippingLabel['city'];
            $shippingAddress->state = $request->shippingLabel['state'] ?? null;
            $shippingAddress->zip = $request->shippingLabel['zip'];
            $shippingAddress->country = $request->shippingLabel['country'];
            $shippingAddress->save();
        }

        return $this->index($request);
    }

    /**
     * Store Packing Slip
     *
     * Store account packing slip options
     */
    public function storePackingSlip(Request $request)
    {
        $request->validate([
            'email' => 'nullable|string|email|max:190',
            'message' => 'nullable|string|max:190',
            'packingSlipLogo' => 'sometimes|dimensions:width=500,height=500|mimes:jpeg,jpg|nullable'
        ]);

        $account = Account::where('id', Auth::user()->account_id)->first();
        $shippingLabel = $account->shippingLabel;
        if (!$shippingLabel) {
            $shippingLabel = new AccountShippingLabel();
            $account->shippingLabel()->save($shippingLabel);
            $shippingLabel->refresh();
        }

        $this->imageHandler($request, 'packingSlipLogo', 'jpg', 'Packing Slip Logo');

        $shippingLabel->email = $request->email;
        $shippingLabel->message = $request->message;
        $shippingLabel->save();

        return $this->index($request);
    }


    /**
     * Store Premium Canvas Options
     *
     * Store account premium canvas options: Card Front / Card Back / Box Sticker / Back Logo
     */
    public function storePremiumCanvas(Request $request)
    {
        $request->validate([
            'cardFront' => 'sometimes|nullable|dimensions:width=1500,height=2400|mimes:jpeg,jpg',
            'cardBack' => 'sometimes|nullable|dimensions:width=1500,height=2400|mimes:jpeg,jpg',
            'boxSticker' => 'sometimes|nullable|dimensions:width=1275,height=1875|mimes:jpeg,jpg',
            'backLogo' => 'sometimes|nullable|dimensions:width=360,height=127|mimes:jpeg,jpg',
        ]);

        if (!empty($request->cardFront)) {
            $this->imageHandler($request, 'cardFront', 'jpg',
                'Insert Card Front');
        } else {
            $this->imageHandler($request, 'cardFront', 'jpg',
                'Insert Card Front');
        }

        if (!empty($request->cardBack)) {
            $this->imageHandler($request, 'cardBack', 'jpg',
                'Insert Card Back');
        } else {
            $this->imageHandler($request, 'cardBack', 'jpg',
                'Insert Card Back');
        }

        if (!empty($request->boxSticker)) {
            $this->imageHandler($request, 'boxSticker', 'jpg',
                'Outside Box Sticker');
        } else {
            $this->imageHandler($request, 'boxSticker', 'jpg',
                'Outside Box Sticker');
        }

        if (!empty($request->backLogo)) {
            $this->imageHandler($request, 'backLogo', 'jpg',
                'Canvas Back Logo');
        } else {
            $this->imageHandler($request, 'backLogo', 'jpg',
                'Canvas Back Logo');
        }

        return $this->index($request);
    }

    /**
     * Update Premium Canvas Options
     *
     * Update account premium canvas options: Card Front / Card Back / Box Sticker / Back Logo
     */
    public function updatePremiumCanvas(Request $request, $id)
    {
        $request->validate([
            'cardFront' => 'sometimes|nullable|dimensions:width=1500,height=2400|mimes:jpeg,jpg',
            'cardBack' => 'sometimes|nullable|dimensions:width=1500,height=2400|mimes:jpeg,jpg',
            'boxSticker' => 'sometimes|nullable|dimensions:width=1275,height=1875|mimes:jpeg,jpg',
            'backLogo' => 'sometimes|nullable|dimensions:width=360,height=127|mimes:jpeg,jpg',
        ]);

        if (!empty($request->cardFront)) {
            $this->imageHandler($request, 'cardFront', 'jpg',
                'Insert Card Front');
        } else {
            $this->imageHandler($request, 'cardFront', 'jpg',
                'Insert Card Front');
        }

        if (!empty($request->cardBack)) {
            $this->imageHandler($request, 'cardBack', 'jpg',
                'Insert Card Back');
        } else {
            $this->imageHandler($request, 'cardBack', 'jpg',
                'Insert Card Back');
        }

        if (!empty($request->boxSticker)) {
            $this->imageHandler($request, 'boxSticker', 'jpg',
                'Outside Box Sticker');
        } else {
            $this->imageHandler($request, 'boxSticker', 'jpg',
                'Outside Box Sticker');
        }

        if (!empty($request->backLogo)) {
            $this->imageHandler($request, 'backLogo', 'jpg',
                'Canvas Back Logo');
        } else {
            $this->imageHandler($request, 'backLogo', 'jpg',
                'Canvas Back Logo');
        }

        return $this->index($request);
    }

    /**
     * Remove the Account Branding Images from storage.
     *
     * @param int $id
     * @return Response
     */
    public function destroyAccountBrandingImages($id)
    {
        try {
            $accountBrandingImage = AccountBrandingImage::findOrFail($id);

            if ($accountBrandingImage->deleteFileAndRemoveFromDB()) {
                return $this->responseOk();
            }
        } catch (ModelNotFoundException $e) {
            return $this->responseNotFound();
        } catch (\Exception $e) {
            Log::error($e);
            return $this->responseServerError();
        }

        return $this->responseServerError();
    }

    /**
     * Store or update images
     *
     * @param Request $request
     * @param $fileKey
     * @param $fileExtension
     * @param $brandingImageTypeName
     * @return AccountResource|Response
     */
    public function imageHandler(Request $request, $fileKey, $fileExtension, $brandingImageTypeName)
    {
        try {
            // check to see image exists
            if ($request->hasFile($fileKey) && $request->file($fileKey)) {
                $accountBrandingImageTypeId = AccountBrandingImageType::where('name', '=', $brandingImageTypeName)->get('id');

                $imageTypeId = ImageType::where('file_extension', '=', $fileExtension)->get('id');

                // check to see if row exists
                $accountBrandingImage = AccountBrandingImage::where([
                    ['account_id', '=', Auth::user()->account_id],
                    ['account_branding_image_type_id', '=', $accountBrandingImageTypeId[0]->id]
                ])->first();

                if ($accountBrandingImage) {
                    // edit
                    $accountBrandingImage->update([
                        'account_branding_image_type_id' => $accountBrandingImageTypeId[0]->id,
                        'image_type_id' => $imageTypeId[0]->id,
                    ]);

                    $accountBrandingImage->saveFileFromRequest($request->file($fileKey), "$brandingImageTypeName.$fileExtension", null, $isPublic = true);
                    $accountBrandingImage->save();
                } else {
                    // create
                    $accountBrandingImage = AccountBrandingImage::create([
                        'account_id' => Auth::user()->account_id,
                        'account_branding_image_type_id' => $accountBrandingImageTypeId[0]->id,
                        'image_type_id' => $imageTypeId[0]->id,
                    ]);

                    $accountBrandingImage->saveFileFromRequest($request->file($fileKey), null, null, $isPublic = true);
                }
                return $accountBrandingImage;
            }
        } catch (\Exception $e) {
            Log::error($e);
            return $this->responseServerError();
        }
    }

    public function getAccountAddresses()
    {
        $addressIds = Order::withoutGlobalScopes(['art', 'logs', 'lineItems', 'storePlatform', 'payments'])
            ->pluck('shipping_address_id');

        $addresses = Address::get()->whereIn('id', $addressIds);

        return response()->json(
            ['data' => array_flatten($addresses)]
        );
    }

    public function changePassword(Request $request)
    {
        $request->validate([
            'currentPassword' => 'required|string',
            'password' => ['required', 'string', 'min:8', 'regex:/^(?=.*[A-Z])(?=.*[a-z])(?=.*[0-9])(.{8,})$/', 'confirmed']
        ]);
        if (Hash::check($request->currentPassword, Auth::user()->password) == false) {
            return response(['message' => 'Make sure your password is correct'], 401);
        }

        $token = Str::random(60);
        $minutes = 60 * 60 * 24 * 365;

        $user = Auth::user();
        $user->password = Hash::make($request->password);
        $user->api_token = $token;
        $user->save();

        return response($user->makeVisible('api_token'), 200)->cookie('token', encrypt($token), $minutes);
    }

    public function changeEmail(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'current_email' => 'required|string',
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users', 'confirmed'],
        ]);

        if ($validator->fails()) {
            return response(['errors' => $validator->errors()->all()], 422);
        }

        if(User::where('email',$request->email)->first()){
            return $this->responseUnprocessableEntity('Email already in use, please enter a different email');
        }

        if (Auth::user()->email != $request->current_email) {
            return $this->responseUnprocessableEntity('Make sure your email is correct');
        }

        $token = Str::random(60);
        $minutes = 60 * 60 * 24 * 365;

        $user = Auth::user();
        $user->email = $request->email;
        $user->email_verified_at = null;
        $user->api_token = $token;
        $user->hash_code = md5(Crypt::encryptString($user->id . Str::random(5)));
        $user->save();

        $account = Auth::user()->account;
        $account->email_verified = 0;
        $account->save();

        $user->sendEmailVerificationNotification();

        $user->refresh();

        return response($user->makeVisible('api_token'), 201)->cookie('token', encrypt($token), $minutes);
    }

    /**
     * Generate account api token
     */
    public function generatePublicApiToken(Request $request){
        $user = User::where('id', Auth::user()->id)->first();
        $token = Str::random(60);
        $user->public_token = !empty($user->public_token) ? $user->public_token : $token;
        $user->save();

        return $this->responseOk(['token' => $token]);
    }

    /**
     * Revoke account api token
     */
    public function revokePublicApiToken(Request $request){
        $user = User::where('id', Auth::user()->id)->first();
        $user->public_token = null;
        $user->save();

        return $this->responseOk(['token' => '']);
    }
}

