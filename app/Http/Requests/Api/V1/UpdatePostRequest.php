<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;

class UpdatePostRequest extends FormRequest
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
        $postId = $this->route('post')?->id ?? null;

        return [
            'title' => ['sometimes', 'required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', 'unique:posts,slug,'.$postId.',id'],
            'excerpt' => ['nullable', 'string'],
            'content' => ['sometimes', 'required', 'array'],
            'cover_image' => ['nullable', 'string'],
            'category' => ['sometimes', 'required', 'string', 'max:255'],
            'tags' => ['nullable', 'array'],
            'author' => ['sometimes', 'required', 'array'],
            'author.name' => ['required_with:author', 'string', 'max:255'],
            'author.thumbnail' => ['nullable', 'string'],
            'list' => ['nullable', 'array'],
            'published_at' => ['nullable', 'date'],
            'reading_time' => ['nullable', 'integer', 'min:0'],
            'featured' => ['nullable', 'boolean'],
            'status' => ['sometimes', 'required', 'in:draft,published'],
        ];
    }
}
