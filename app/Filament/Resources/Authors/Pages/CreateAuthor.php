<?php

namespace App\Filament\Resources\Authors\Pages;

use App\Filament\Resources\Authors\AuthorResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreateAuthor extends CreateRecord
{
    protected static string $resource = AuthorResource::class;

    protected function getFormActions(): array
    {
        return [
            Actions\Action::make('randomName')
                ->label('Nombre aleatorio')
                ->icon('heroicon-o-sparkles')
                ->color('gray')
                ->action(function (): void {
                    $state = $this->form->getState();
                    $state['name'] = fake()->name();
                    $this->form->fill($state);
                }),
            $this->getCreateFormAction(),
            $this->getCancelFormAction(),
        ];
    }
}
