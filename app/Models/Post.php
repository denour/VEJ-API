<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
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
}
