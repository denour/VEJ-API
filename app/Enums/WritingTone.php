<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum WritingTone: string implements HasLabel
{
    case Warm = 'warm';
    case Neutral = 'neutral';
    case Authoritative = 'authoritative';
    case Playful = 'playful';
    case Nurturing = 'nurturing';
    case Enthusiastic = 'enthusiastic';

    public function getLabel(): string
    {
        return match ($this) {
            self::Warm => 'Warm & Friendly',
            self::Neutral => 'Neutral & Balanced',
            self::Authoritative => 'Authoritative & Expert',
            self::Playful => 'Playful & Light',
            self::Nurturing => 'Nurturing & Supportive',
            self::Enthusiastic => 'Enthusiastic & Energetic',
        };
    }

    public function getDescription(): string
    {
        return match ($this) {
            self::Warm => 'Like talking to a friendly neighbor who genuinely cares',
            self::Neutral => 'Informative without strong emotional coloring',
            self::Authoritative => 'Confident expertise, speaks with certainty',
            self::Playful => 'Light-hearted, uses humor and wit',
            self::Nurturing => 'Encouraging, supportive, patient teacher',
            self::Enthusiastic => 'Excited, passionate, high energy',
        };
    }
}
