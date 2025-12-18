<?php

namespace App\Filament\Resources\ImageGenerationRequests\Pages;

use App\Filament\Resources\ImageGenerationRequests\ImageGenerationRequestResource;
use Filament\Resources\Pages\CreateRecord;

class CreateImageGenerationRequest extends CreateRecord
{
    protected static string $resource = ImageGenerationRequestResource::class;
}
