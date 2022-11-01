<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\ResourceCollection;
use SunriseIntegration\TeelaunchModels\Utils\Formatters\SnakeToCamelCase;

class BaseResourceCollection extends ResourceCollection {

    public function toArray($request)
    {
        return SnakeToCamelCase::convertKeysToCamelCase(parent::toArray($request));
    }
}
