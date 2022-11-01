<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Controllers\Controller;
use SunriseIntegration\TeelaunchModels\Models\WeightUnit;

class WeightUnitController extends Controller
{
    public function index()
    {
        return WeightUnit::all();
    }
}
