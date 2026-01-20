<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum Formality: string implements HasLabel
{
    case Casual = 'casual';
    case Balanced = 'balanced';
    case Formal = 'formal';

    public function getLabel(): string
    {
        return match ($this) {
            self::Casual => 'Casual',
            self::Balanced => 'Balanced',
            self::Formal => 'Formal',
        };
    }

    public function getDescription(): string
    {
        return match ($this) {
            self::Casual => 'Relaxed, informal tone. Uses contractions, colloquialisms.',
            self::Balanced => 'Professional but approachable. Standard business writing.',
            self::Formal => 'Polished, proper language. Avoids slang and contractions.',
        };
    }
}
