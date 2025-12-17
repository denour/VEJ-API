<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    protected $fillable = [
        'site_name',
        'phone',
        'address',
        'socials',
        'logo',
        'favicon',
    ];

    protected function casts(): array
    {
        return [
            'socials' => 'array',
        ];
    }
}
