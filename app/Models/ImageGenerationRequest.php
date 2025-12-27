<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

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

    protected function imagePath(): Attribute
    {
        return Attribute::make(
            get: fn (?string $value) => $value ? Storage::disk('s3')->url($value) : null,
            set: fn (?string $value) => $value,
        );
    }

    protected function imageUrl(): Attribute
    {
        return Attribute::make(
            get: fn (?string $value) => $value ? Storage::disk('s3')->url($value) : null,
            set: fn (?string $value) => $value,
        );
    }

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

    /**
     * Check if a pending or processing request already exists for the given target and attribute.
     */
    public static function hasPendingRequest(string $targetableType, string $targetableId, string $attribute): bool
    {
        return static::query()
            ->where('targetable_type', $targetableType)
            ->where('targetable_id', $targetableId)
            ->whereIn('status', ['pending', 'processing'])
            ->whereJsonContains('metadata->attribute', $attribute)
            ->exists();
    }
}
