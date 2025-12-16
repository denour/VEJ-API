<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;

class StorePostRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $input = [];
        foreach ($this->all() as $key => $value) {
            $input[Str::snake($key)] = $value;
        }

        $this->replace($input);
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', 'unique:posts,slug'],
            'excerpt' => ['nullable', 'string'],
            'content' => ['required', 'array'],
            'cover_image' => ['nullable', 'string'],
            'category' => ['required', 'string', 'max:255'],
            'tags' => ['nullable', 'array'],
            'author' => ['required', 'array'],
            'author.name' => ['required', 'string', 'max:255'],
            'author.thumbnail' => ['nullable', 'string'],
            'list' => ['nullable', 'array'],
            'published_at' => ['nullable', 'date'],
            'reading_time' => ['nullable', 'integer', 'min:0'],
            'featured' => ['nullable', 'boolean'],
            'status' => ['required', 'in:draft,published'],
        ];
    }
}
