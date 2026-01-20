<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Author extends Model
{
    use HasFactory;

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
