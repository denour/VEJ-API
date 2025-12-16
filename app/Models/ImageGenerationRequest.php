<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ImageGenerationRequest extends Model
{
    protected $fillable = [
        'external_id',
        'token',
        'prompt',
        'size',
        'status',
        'image_path',
        'image_url',
        'error_message',
        'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
    ];

    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    public function isPending(): bool
    {
        return in_array($this->status, ['pending', 'processing']);
    }
}
