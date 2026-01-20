<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PostBlock extends Model
{
    use HasFactory;
    use HasUlids;

    protected $fillable = [
        'post_id',
        'type',
        'title',
        'content',
        'data',
        'order',
    ];

    protected function casts(): array
    {
        return [
            'data' => 'array',
            'order' => 'integer',
        ];
    }

    public function post(): BelongsTo
    {
        return $this->belongsTo(Post::class);
    }

    /**
     * Get placeholder image URL for image blocks without a generated image.
     */
    public function getImageUrl(): ?string
    {
        if ($this->type !== 'image') {
            return null;
        }

        return $this->data['url'] ?? 'https://placehold.co/800x600/e2e8f0/64748b?text=Generando+Imagen...';
    }

    /**
     * Check if this block has a pending image generation.
     */
    public function hasPendingImage(): bool
    {
        return $this->type === 'image' && empty($this->data['url']);
    }
}
