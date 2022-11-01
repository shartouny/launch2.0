<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;
use SunriseIntegration\TeelaunchModels\Utils\Formatters\SnakeToCamelCase;

class BaseResource extends JsonResource {

    public function toArray($request)
    {
        return SnakeToCamelCase::convertKeysToCamelCase(parent::toArray($request));
    }
}
