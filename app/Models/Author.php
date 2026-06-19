<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Author extends Model
{
    use HasFactory;
    use HasUlids;

    protected static function booted(): void
    {
        static::creating(function (Author $author): void {
            if (blank($author->slug) && filled($author->name)) {
                $base = Str::slug($author->name);
                $slug = $base;
                $i = 2;
                while (static::query()->where('slug', $slug)->exists()) {
                    $slug = "{$base}-{$i}";
                    $i++;
                }
                $author->slug = $slug;
            }
        });
    }

    protected $fillable = [
        'name',
        'slug',
        'is_active',
        'background_story',
        'personality_traits',
        'expertise_areas',
        'sentence_style',
        'vocabulary_level',
        'tone',
        'formality',
        'catchphrases',
        'quirks',
        'recurring_topics',
        'avoided_elements',
        'voice_bible',
        'sample_paragraph',
        'avatar_url',
        'generation_stats',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'personality_traits' => 'array',
            'expertise_areas' => 'array',
            'catchphrases' => 'array',
            'quirks' => 'array',
            'recurring_topics' => 'array',
            'avoided_elements' => 'array',
            'generation_stats' => 'array',
        ];
    }

    public function posts(): HasMany
    {
        return $this->hasMany(Post::class);
    }

    public function topics(): HasMany
    {
        return $this->hasMany(AuthorTopic::class);
    }

    public function nextAvailableTopic(): ?AuthorTopic
    {
        return $this->topics()->unused()->oldest('created_at')->first();
    }

    public function unusedTopicCount(): int
    {
        return $this->topics()->unused()->count();
    }

    public function isComplete(): bool
    {
        return filled($this->voice_bible)
            && filled($this->background_story)
            && filled($this->personality_traits);
    }

    public function incrementPostCount(): void
    {
        $stats = $this->generation_stats ?? [];
        $stats['posts_generated'] = ($stats['posts_generated'] ?? 0) + 1;
        $stats['last_used_at'] = now()->toIso8601String();
        $this->update(['generation_stats' => $stats]);
    }
}
