<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ShippingZone extends Model
{
    use HasFactory;
    use HasUlids;

    protected $fillable = [
        'zone',
        'time',
        'cost',
        'regular_cost',
        'currency',
    ];

    protected function casts(): array
    {
        return [
            'cost' => 'decimal:2',
            'regular_cost' => 'decimal:2',
        ];
    }
}
