<?php

namespace App\Filament\Resources\SpeciesResource\Pages;

use App\Filament\Resources\SpeciesResource;
use App\Services\AI\SpeciesGeneratorService;
use Filament\Actions;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;

class ListSpecies extends ListRecords
{
    protected static string $resource = SpeciesResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
            Actions\Action::make('generateSpecies')
                ->label('Generar Especie')
                ->icon('heroicon-o-sparkles')
                ->modalHeading('Generar Especie con IA')
                ->form([
                    Forms\Components\TextInput::make('title')
                        ->label('Título / Nombre de la especie')
                        ->placeholder('Ej. Ficus lyrata (higuera de hojas de violín)')
                        ->required(),
                ])
                ->action(function (array $data) {
                    /** @var SpeciesGeneratorService $service */
                    $service = app(SpeciesGeneratorService::class);
                    $species = $service->generate($data['title']);

                    Notification::make()
                        ->title('Especie generada correctamente')
                        ->success()
                        ->send();

                    return redirect(SpeciesResource::getUrl('edit', ['record' => $species]));
                }),
        ];
    }
}
