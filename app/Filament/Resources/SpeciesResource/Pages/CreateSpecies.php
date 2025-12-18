<?php

namespace App\Filament\Resources\SpeciesResource\Pages;

use App\Filament\Resources\SpeciesResource;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;

class CreateSpecies extends CreateRecord
{
    protected static string $resource = SpeciesResource::class;

    protected function afterCreate(): void
    {
        // Check if image was not uploaded manually
        if (empty($this->record->image)) {
            Notification::make()
                ->title('Especie creada')
                ->body('La imagen se está generando en segundo plano. Recibirás una notificación cuando esté lista.')
                ->success()
                ->send();
        }
    }
}
