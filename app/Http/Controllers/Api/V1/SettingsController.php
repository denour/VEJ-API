<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Resources\SettingResource;
use App\Models\Setting;
use Illuminate\Http\Request;

class SettingsController
{
    public function show(Request $request): AnonymousResourceCollection
    {
        $settings = Setting::all();

        return SettingResource::collection($settings);
    }
}
