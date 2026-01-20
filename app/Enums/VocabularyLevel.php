<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum VocabularyLevel: string implements HasLabel
{
    case Simple = 'simple';
    case Conversational = 'conversational';
    case Technical = 'technical';
    case Academic = 'academic';

    public function getLabel(): string
    {
        return match ($this) {
            self::Simple => 'Simple & Clear',
            self::Conversational => 'Conversational',
            self::Technical => 'Technical',
            self::Academic => 'Academic',
        };
    }

    public function getDescription(): string
    {
        return match ($this) {
            self::Simple => 'Everyday words anyone can understand',
            self::Conversational => 'Natural, friendly language with some specialized terms',
            self::Technical => 'Industry-specific terminology for knowledgeable readers',
            self::Academic => 'Formal, precise language with scientific terminology',
        };
    }
}
