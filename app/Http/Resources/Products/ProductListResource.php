<?php

namespace App\Http\Resources\Products;

use Illuminate\Http\Resources\Json\JsonResource;

class ProductListResource extends JsonResource
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
            'mainImageThumbUrl' => $this->mainImageThumbUrl,
            'name' => $this->name,
            'image' => $this->variants[0]->blankVariant ? $this->variants[0]->blankVariant->thumbnail : $this->variants[0]->thumbnail,
            'category' => $this->variants[0]->blankVariant ? $this->variants[0]->blankVariant->blank->blank_category_id : null
        ];
    }
}
