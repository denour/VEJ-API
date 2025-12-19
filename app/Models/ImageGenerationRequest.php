<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ImageGenerationRequest extends Model
{
    protected $fillable = [
        'external_id',
        'post_id',
        'targetable_type',
        'targetable_id',
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

    public function post(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Post::class);
    }

    public function targetable(): \Illuminate\Database\Eloquent\Relations\MorphTo
    {
        return $this->morphTo();
    }
}
