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
            'site_name' => $this->site_name,
            'phone' => $this->phone,
            'address' => $this->address,
            'socials' => $this->socials ?? new \stdClass(),
            'logo' => $this->logo,
            'favicon' => $this->favicon,
            'updated_at' => optional($this->updated_at)?->toIso8601String(),
        ];
    }
}
