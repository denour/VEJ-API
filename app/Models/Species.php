<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Species extends Model
{
    use HasFactory;
    use HasUlids;

    protected $fillable = [
        'common_name',
        'scientific_name',
        'family',
        'origin',
        'description',
        'care_level',
        'sunlight',
        'watering',
        'image',
        'images',
        'toxicity',
        'growth_rate',
        'max_height_cm',
    ];

    protected function casts(): array
    {
        return [
            'images' => 'array',
            'max_height_cm' => 'integer',
        ];
    }
}
