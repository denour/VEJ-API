<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class Post extends Model
{
    use HasFactory;
    use HasUlids;

    protected $fillable = [
        'title',
        'slug',
        'excerpt',
        'content',
        'cover_image',
        'category',
        'tags',
        'author_id',
        'list',
        'published_at',
        'reading_time',
        'featured',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'content' => 'array',
            'tags' => 'array',
            'list' => 'array',
            'published_at' => 'datetime',
            'featured' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $post): void {
            if (empty($post->slug) && ! empty($post->title)) {
                $post->slug = Str::slug($post->title);
            }
        });
    }

//    protected function coverImage(): Attribute
//    {
//        return Attribute::make(
//            get: fn (?string $value) => $value ? Storage::disk('s3')->url($value) : null,
//            set: fn (?string $value) => $value,
//        );
//    }
//
    public function author(): BelongsTo
    {
        return $this->belongsTo(Author::class);
    }

    public function blocks(): HasMany
    {
        return $this->hasMany(PostBlock::class)->orderBy('order');
    }

    /**
     * Generate legacy content format from blocks for backward compatibility.
     */
    public function getContentFromBlocks(): array
    {
        return $this->blocks->map(function (PostBlock $block) {
            $content = [
                'type' => $block->type,
            ];

            switch ($block->type) {
                case 'paragraph':
                    $content['data'] = ['text' => $block->content];
                    break;
                case 'heading':
                    $content['data'] = [
                        'text' => $block->title,
                        'level' => $block->data['level'] ?? 2,
                    ];
                    break;
                case 'image':
                    $content['data'] = $block->data;
                    break;
                case 'list':
                    $content['data'] = $block->data;
                    break;
                case 'quote':
                    $content['data'] = array_merge(
                        ['text' => $block->content],
                        $block->data ?? []
                    );
                    break;
                case 'code':
                    $content['data'] = array_merge(
                        ['code' => $block->content],
                        $block->data ?? []
                    );
                    break;
                case 'video':
                    $content['data'] = $block->data;
                    break;
            }

            return $content;
        })->toArray();
    }

    /**
     * Auto-generate table of contents from heading blocks.
     */
    public function generateTableOfContents(): array
    {
        return $this->blocks()
            ->where('type', 'heading')
            ->get()
            ->map(function (PostBlock $block, int $index) {
                return [
                    'id' => $index,
                    'text' => $block->title,
                    'level' => $block->data['level'] ?? 2,
                ];
            })
            ->toArray();
    }
}
