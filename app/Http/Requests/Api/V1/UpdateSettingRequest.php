<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class UpdateSettingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Ajustar si se requiere auth
    }

    public function rules(): array
    {
        return [
            'site_name' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:255'],
            'address' => ['nullable', 'string', 'max:1000'],
            'socials' => ['nullable', 'array'],
            'socials.*' => ['nullable', 'string', 'max:1000'],
            'logo' => ['nullable', 'string', 'max:1000'],
            'favicon' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
