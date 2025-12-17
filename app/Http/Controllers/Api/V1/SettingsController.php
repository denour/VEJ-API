<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Api\V1\UpdateSettingRequest;
use App\Http\Resources\SettingResource;
use App\Models\Setting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SettingsController
{
    public function show(Request $request): SettingResource
    {
        $settings = Setting::query()->first();

        if (! $settings) {
            $settings = Setting::query()->create([
                'site_name' => null,
                'phone' => null,
                'address' => null,
                'socials' => [],
                'logo' => null,
                'favicon' => null,
            ]);
        }

        return new SettingResource($settings);
    }

    public function update(UpdateSettingRequest $request): JsonResponse
    {
        $settings = Setting::query()->first();
        if (! $settings) {
            $settings = new Setting();
        }

        $settings->fill($request->validated());
        $settings->save();

        return (new SettingResource($settings))
            ->response()
            ->setStatusCode(200);
    }
}
