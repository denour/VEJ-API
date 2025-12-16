<?php

namespace App\Filament\Resources\Authors\Schemas;

use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class AuthorForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                FileUpload::make('image')
                    ->image()
                    ->disk('public')
                    ->visibility('public')
                    ->directory('authors'),
                TextInput::make('name')
                    ->required(),
                Textarea::make('description')
                    ->default(null)
                    ->columnSpanFull(),
                Textarea::make('detailed_description')
                    ->default(null)
                    ->columnSpanFull(),
            ]);
    }
}
