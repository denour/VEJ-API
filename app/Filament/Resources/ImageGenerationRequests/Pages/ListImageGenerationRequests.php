<?php

namespace App\Filament\Resources\ImageGenerationRequests\Pages;

use App\Filament\Resources\ImageGenerationRequests\ImageGenerationRequestResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListImageGenerationRequests extends ListRecords
{
    protected static string $resource = ImageGenerationRequestResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
