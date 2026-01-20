<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum SentenceStyle: string implements HasLabel
{
    case Short = 'short';
    case Medium = 'medium';
    case Varied = 'varied';
    case Long = 'long';

    public function getLabel(): string
    {
        return match ($this) {
            self::Short => 'Short & Punchy',
            self::Medium => 'Medium Length',
            self::Varied => 'Varied Mix',
            self::Long => 'Long & Flowing',
        };
    }

    public function getDescription(): string
    {
        return match ($this) {
            self::Short => 'Quick, direct sentences. Keeps things moving.',
            self::Medium => 'Balanced sentence length. Most common style.',
            self::Varied => 'Mix of short and long. Creates rhythm.',
            self::Long => 'Complex, flowing sentences with multiple clauses.',
        };
    }
}
