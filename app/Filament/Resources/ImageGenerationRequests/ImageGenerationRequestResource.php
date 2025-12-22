<?php

namespace App\Filament\Resources\ImageGenerationRequests;

use App\Filament\Resources\ImageGenerationRequests\Pages\CreateImageGenerationRequest;
use App\Filament\Resources\ImageGenerationRequests\Pages\EditImageGenerationRequest;
use App\Filament\Resources\ImageGenerationRequests\Pages\ListImageGenerationRequests;
use App\Filament\Resources\ImageGenerationRequests\Schemas\ImageGenerationRequestForm;
use App\Filament\Resources\ImageGenerationRequests\Tables\ImageGenerationRequestsTable;
use App\Models\ImageGenerationRequest;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class ImageGenerationRequestResource extends Resource
{
    protected static ?string $model = ImageGenerationRequest::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::Photo;

    protected static ?string $modelLabel = 'Image Generation Request';

    protected static ?string $pluralModelLabel = 'Image Generation Requests';

    public static function form(Schema $schema): Schema
    {
        return ImageGenerationRequestForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ImageGenerationRequestsTable::configure($table);
    }

    public static function configure(Table $table): Table
    {
        return $table
            ->poll('20s')
            ->defaultSort('created_at', 'asc');
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListImageGenerationRequests::route('/'),
            'create' => CreateImageGenerationRequest::route('/create'),
            'edit' => EditImageGenerationRequest::route('/{record}/edit'),
        ];
    }
}
