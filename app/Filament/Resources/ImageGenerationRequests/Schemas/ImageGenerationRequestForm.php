<?php

namespace App\Filament\Resources\ImageGenerationRequests\Schemas;

use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Schema;

class ImageGenerationRequestForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('external_id')
                    ->default(null),
                Select::make('post_id')
                    ->relationship('post', 'title'),
                TextInput::make('targetable_type')
                    ->default(null),
                TextInput::make('targetable_id')
                    ->default(null),
                TextInput::make('token')
                    ->required(),
                Textarea::make('prompt')
                    ->required()
                    ->columnSpanFull(),
                TextInput::make('size')
                    ->required()
                    ->default('1024x1024'),
                Select::make('status')
                    ->options([
            'pending' => 'Pending',
            'processing' => 'Processing',
            'completed' => 'Completed',
            'failed' => 'Failed',
        ])
                    ->default('pending')
                    ->required(),
                FileUpload::make('image_path')
                    ->image(),
                FileUpload::make('image_url')
                    ->image(),
                Textarea::make('error_message')
                    ->label('Error Message')
                    ->default(null)
                    ->columnSpanFull()
                    ->rows(3),
                KeyValue::make('metadata')
                    ->label('Metadata')
                    ->default(null)
                    ->columnSpanFull()
                    ->addable(false)
                    ->deletable(false)
                    ->editableKeys(false),
            ]);
    }
}
