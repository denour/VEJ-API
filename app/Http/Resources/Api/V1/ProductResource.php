<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => (string) $this->id,
            'image' => $this->image,
            'images' => $this->images ?? [],
            'name' => $this->name,
            'scientificName' => $this->scientific_name,
            'careLevel' => $this->care_level,
            'sunlight' => $this->sunlight,
            'watering' => $this->watering,
            'condition' => $this->condition,
            'size' => $this->size,
            'isRare' => (bool) $this->is_rare,
            'type' => $this->type,
            'price' => $this->price,
            'currency' => $this->currency,
            'rating' => $this->rating,
            'reviews' => $this->reviews,
            'inStock' => (bool) $this->in_stock,
            'quantity' => $this->quantity,
            'createdAt' => optional($this->created_at)?->toISOString(),
            'updatedAt' => optional($this->updated_at)?->toISOString(),
        ];
    }
}
