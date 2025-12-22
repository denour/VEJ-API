<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

class Author extends Model
{
    use HasFactory;

    protected $fillable = [
        'image',
        'name',
        'description',
        'detailed_description',
    ];

    protected function casts(): array
    {
        return [
            'detailed_description' => 'array',
        ];
    }

    protected function image(): Attribute
    {
        return Attribute::make(
            get: fn (?string $value) => $value ? Storage::disk('s3')->url($value) : null,
            set: fn (?string $value) => $value,
        );
    }

    public function posts(): HasMany
    {
        return $this->hasMany(Post::class);
    }
}
