<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    protected $fillable = [
        'key',
        'value',
        'type',
    ];

    protected function casts(): array
    {
        return [
            'value' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (AppSetting $setting): void {
            if ($setting->type === null || $setting->type === '') {
                $setting->type = 'string';
            }
        });
    }

    public function getTypedValue(): mixed
    {
        $val = $this->value;

        // Guardar en BD como JSON, pero exponer según el tipo
        return match ($this->type) {
            'boolean' => (bool) ($val['value'] ?? false),
            'integer' => (int) ($val['value'] ?? 0),
            'float' => (float) ($val['value'] ?? 0.0),
            'array' => $val['value'] ?? [],
            default => (string) ($val['value'] ?? ''),
        };
    }
}
