<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SpeciesResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => (string) $this->id,
            'commonName' => $this->common_name,
            'scientificName' => $this->scientific_name,
            'family' => $this->family,
            'origin' => $this->origin,
            'description' => $this->description,
            'careLevel' => $this->care_level,
            'sunlight' => $this->sunlight,
            'watering' => $this->watering,
            'image' => url('storage/' . $this->image),
            'images' => $this->images ?? [],
            'toxicity' => $this->toxicity,
            'growthRate' => $this->growth_rate,
            'maxHeightCm' => $this->max_height_cm,
            'createdAt' => optional($this->created_at)?->toISOString(),
            'updatedAt' => optional($this->updated_at)?->toISOString(),
        ];
    }
}
