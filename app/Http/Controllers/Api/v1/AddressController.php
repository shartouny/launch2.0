<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Controllers\Controller;
use App\Models\Orders\Address;
use App\Models\Orders\OrderLog;
use Illuminate\Http\Request;

class AddressController extends Controller
{
    public function update(Address $address, Request $request)
    {
        $this->validate($request, [
            'fullName' => ['required', 'string'],
            'address1' => ['required', 'string'],
            'address2' => ['string', 'nullable'],
            'city' => ['required', 'string', 'max:30'],
            'state' => ['string', 'max:30', 'nullable'],
            'zip' => ['string', 'nullable'],
            'country' => ['required', 'string'],
            'phone' => ['string', 'nullable']
        ]);

        $fullName = explode(' ', $request->fullName);

        $executed = $address->update([
            'first_name' => $fullName[0],
            'last_name' => $fullName[1] ?? '',
            'address1' => $request->input('address1'),
            'address2' => $request->input('address2', null),
            'city' => $request->input('city'),
            'state' => $request->input('state'),
            'zip' => $request->input('zip'),
            'phone' => $request->input('phone'),
            'country' => $request->input('country')
        ]);

        if (!$executed) {
            return response()->json([
                'success' => false,
                'message' => 'Something went wrong when updating address'
            ], 500);
        }

        $log = $address->order_shipping->logs()->create([
            'message' => 'Order shipping address was updated',
            'message_type' => OrderLog::MESSAGE_TYPE_INFO
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Address updated successfully',
            'log' => $log
        ]);
    }
}
