<?php

namespace App\Http\Resources\Blanks;

use Illuminate\Http\Resources\Json\JsonResource;

class BlankCategoryProductResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'thumbnail' => $this->thumbnail,
            'description' => $this->description,
        ];
    }
}
