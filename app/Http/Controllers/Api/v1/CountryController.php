<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Controllers\Controller;
use App\Http\Resources\CountryCollectionResource;
use App\Models\Countries\Country;
use Illuminate\Http\Request;

class CountryController extends Controller {

    /**
     * @param Request $request
     * @return CountryCollectionResource
     */
    public function index (Request $request){
        $countries = Country::select('name','code')->get();
        return new CountryCollectionResource($countries);
    }
}
