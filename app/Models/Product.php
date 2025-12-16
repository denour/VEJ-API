<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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

    public function species(): BelongsTo
    {
        return $this->belongsTo(Species::class);
    }
}
