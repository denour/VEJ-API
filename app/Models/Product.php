<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class Product extends Model
{
    use HasFactory;
    use HasUlids;

    protected $fillable = [
        'image',
        'images',
        'name',
        'scientific_name',
        'species_id',
        'care_level',
        'sunlight',
        'watering',
        'condition',
        'size',
        'is_rare',
        'type',
        'price',
        'currency',
        'rating',
        'reviews',
        'in_stock',
        'quantity',
    ];

    protected function casts(): array
    {
        return [
            'images' => 'array',
            'is_rare' => 'boolean',
            'price' => 'decimal:2',
            'rating' => 'decimal:2',
            'reviews' => 'integer',
            'in_stock' => 'boolean',
            'quantity' => 'integer',
        ];
    }

    protected function image(): Attribute
    {
        return Attribute::make(
            get: fn (?string $value) => $value ? Storage::disk('s3')->url($value) : null,
            set: fn (?string $value) => $value,
        );
    }

    protected function images(): Attribute
    {
        return Attribute::make(
            get: fn (?array $value) => $value ? array_map(
                fn ($path) => Storage::disk('s3')->url($path),
                $value
            ) : null,
            set: fn (?array $value) => $value,
        );
    }

    public function species(): BelongsTo
    {
        return $this->belongsTo(Species::class);
    }
}
