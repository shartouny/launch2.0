<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Accounts\AccountCollectionResource;
use App\Http\Resources\Accounts\AccountResource;
use App\Models\Accounts\AccountSettings;
use DateTimeZone;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

/**
 * @group  Account
 *
 * APIs for managing account
 */

class AccountSettingsController extends Controller
{
    /**
     * Get Settings
     *
     * Get all account settings
     *
     * @queryParam  q Field to search
     */
    public function index(Request $request)
    {
        if ($request->q) {
            $settings = AccountSettings::where(['key' => $request->q])->get();
        } else {
            $settings = AccountSettings::get();
        }

        return new AccountCollectionResource($settings);
    }

    /**
     * Get Setting
     *
     * Get account setting by id
     * 
     * @urlParam  id  required ID of the Account 
     */
    public function show(Request $request, $id)
    {
        $setting = AccountSettings::findOrFail($id);

        return new AccountResource($setting);
    }

    /**
     * Store Settings
     *
     * Store account settings
     * 
     * @bodyParam  key    string required key
     * @bodyParam  value  string required value
     * 
     */
    public function store(Request $request)
    {
        $request->validate([
            'key' => 'required|string',
            'value' => 'required'
        ]);

        $key = $request->key;
        $value = $request->value;

        if(!AccountSettings::isKeyAllowed($key)){
            return $this->responseBadRequest("Setting $key not allowed");
        }

        try {
            $setting = AccountSettings::updateOrCreate([
                'account_id' => Auth::user()->account_id,
                'key' => $key
            ], [
                'value' => $value
            ]);
        } catch (\Exception $e) {
            Log::error($e);
            return $this->responseServerError();
        }

        return new AccountResource($setting);
    }

    /**
     * Update Setting
     *
     * Update account setting by id
     * 
     * @bodyParam  value  string required value
     * @urlParam  id  required ID of the Account
     */
    public function update(Request $request, $id)
    {
        $request->validate([
            'value' => 'required|string'
        ]);

        $value = $request->value;

        try {
            $setting = AccountSettings::findOrFail($id)->update([['value' => $value]]);
        } catch (\Exception $e) {
            Log::error($e);
            return $this->responseServerError();
        }

        return new AccountResource($setting);
    }

    public function timezones(){
        $tzlist = DateTimeZone::listIdentifiers(DateTimeZone::ALL);
        return new AccountResource($tzlist);
    }
}
