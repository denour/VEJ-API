<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

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
            'max_height_cm' => 'integer',
        ];
    }

//    protected function image(): Attribute
//    {
//        return Attribute::make(
//            get: fn (?string $value) => $value ? Storage::disk('s3')->url($value) : null,
//            set: fn (?string $value) => $value,
//        );
//    }

//    protected function images(): Attribute
//    {
//        return Attribute::make(
//            get: function (?string $value): ?array {
//                if (! $value) {
//                    return null;
//                }
//
//                $paths = json_decode($value, true);
//
//                if (! is_array($paths)) {
//                    return null;
//                }
//
//                return array_map(
//                    fn ($path) => Storage::disk('s3')->url($path),
//                    $paths
//                );
//            },
//            set: fn (?array $value): ?string => $value ? json_encode($value) : null,
//        );
//    }
}
