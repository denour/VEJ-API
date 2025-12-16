<?php

namespace App\Filament\Resources\FaqResource\Pages;

use App\Filament\Resources\FaqResource;
use App\Services\AI\FaqGeneratorService;
use Filament\Actions;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;

class ListFaqs extends ListRecords
{
    protected static string $resource = FaqResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
            Actions\Action::make('generateFaq')
                ->label('Generar FAQ')
                ->icon('heroicon-o-sparkles')
                ->modalHeading('Generar FAQ con IA')
                ->form([
                    Forms\Components\TextInput::make('topic')
                        ->label('Tema o pregunta')
                        ->placeholder('Ej. ¿Cada cuánto debo regar mis suculentas?')
                        ->required(),
                ])
                ->action(function (array $data) {
                    /** @var FaqGeneratorService $service */
                    $service = app(FaqGeneratorService::class);
                    $faq = $service->generate($data['topic']);

                    Notification::make()
                        ->title('FAQ generada correctamente')
                        ->success()
                        ->send();

                    return redirect(FaqResource::getUrl('edit', ['record' => $faq]));
                }),
        ];
    }
}
