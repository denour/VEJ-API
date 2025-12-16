<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ReturnPolicy extends Model
{
    use HasFactory;
    use HasUlids;

    protected $fillable = [
        'title',
        'guarantee_reasons',
        'return_steps',
        'conditions',
        'quick_cards',
    ];

    protected function casts(): array
    {
        return [
            'guarantee_reasons' => 'array',
            'return_steps' => 'array',
            'conditions' => 'array',
            'quick_cards' => 'array',
        ];
    }
}
