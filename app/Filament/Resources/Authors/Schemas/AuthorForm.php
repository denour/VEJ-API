<?php

namespace App\Filament\Resources\Authors\Schemas;

use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class AuthorForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                FileUpload::make('image')
                    ->image()
                    ->disk('s3')
                    ->visibility('public')
                    ->directory('authors')
                    ->storeFileNamesIn('image_filename'),
                TextInput::make('name')
                    ->required(),
                Textarea::make('description')
                    ->default(null)
                    ->columnSpanFull(),
                Section::make('Descripción Detallada')
                    ->description('Define las características del estilo de escritura del autor')
                    ->schema([
                        TextInput::make('detailed_description.tone')
                            ->label('Tono')
                            ->placeholder('conversacional y educativo')
                            ->helperText('Ejemplo: conversacional, técnico, entusiasta, profesional'),
                        TextInput::make('detailed_description.personality')
                            ->label('Personalidad')
                            ->placeholder('entusiasta')
                            ->helperText('Ejemplo: apasionado, educativo, amigable, experto'),
                        TextInput::make('detailed_description.writing_style')
                            ->label('Estilo de Escritura')
                            ->placeholder('claro y accesible')
                            ->helperText('Ejemplo: claro y accesible, técnico y detallado'),
                        TagsInput::make('detailed_description.themes')
                            ->label('Temas Principales')
                            ->placeholder('Agregar tema')
                            ->helperText('Presiona Enter después de cada tema'),
                        TextInput::make('detailed_description.editorial_focus')
                            ->label('Foco Editorial')
                            ->placeholder('educación práctica')
                            ->helperText('Ejemplo: educación práctica, tips avanzados'),
                    ])
                    ->columnSpanFull()
                    ->collapsible(),
            ]);
    }
}
