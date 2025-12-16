<?php

namespace App\Filament\Resources\Authors\Pages;

use App\Filament\Resources\Authors\AuthorResource;
use App\Services\AI\AuthorDescriptionGeneratorService;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditAuthor extends EditRecord
{
    protected static string $resource = AuthorResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('generateDescription')
                ->label('Generar Descripción Detallada')
                ->icon('heroicon-o-sparkles')
                ->requiresConfirmation()
                ->action(function (AuthorDescriptionGeneratorService $service): void {
                    $author = $this->record;

                    if (empty($author->description)) {
                        Notification::make()
                            ->title('Error')
                            ->body('El autor debe tener una descripción básica para generar la descripción detallada.')
                            ->danger()
                            ->send();

                        return;
                    }

                    try {
                        $detailedDescription = $service->generateDetailedDescription($author);
                        $author->update(['detailed_description' => $detailedDescription]);

                        Notification::make()
                            ->title('Descripción generada')
                            ->body('La descripción detallada se generó exitosamente.')
                            ->success()
                            ->send();

                        $this->redirect($this->getResource()::getUrl('edit', ['record' => $author]));
                    } catch (\Exception $e) {
                        Notification::make()
                            ->title('Error al generar descripción')
                            ->body($e->getMessage())
                            ->danger()
                            ->send();
                    }
                }),
            DeleteAction::make(),
        ];
    }
}
