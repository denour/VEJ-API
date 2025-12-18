<?php

namespace App\Filament\Resources\ImageGenerationRequests\Pages;

use App\Filament\Resources\ImageGenerationRequests\ImageGenerationRequestResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditImageGenerationRequest extends EditRecord
{
    protected static string $resource = ImageGenerationRequestResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
