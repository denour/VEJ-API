<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\Setting
 */
class SettingResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'key' => $this->key,
            'value' => $this->getTypedValue(),
            'type' => $this->type,
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
