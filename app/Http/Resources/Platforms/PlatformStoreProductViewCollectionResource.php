<?php

namespace App\Http\Resources\Platforms;

use Illuminate\Http\Resources\Json\JsonResource;

class PlatformStoreProductViewCollectionResource extends JsonResource
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
            'image' => $this->image,
            'title' => $this->title,
            'variants.length' => sizeof($this->variants),
            'is_ignored'=> $this->is_ignored
        ];
    }
}
