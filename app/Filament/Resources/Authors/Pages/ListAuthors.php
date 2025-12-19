<?php

namespace App\Filament\Resources\Authors\Pages;

use App\Filament\Resources\Authors\AuthorResource;
use App\Services\AI\AuthorGeneratorService;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;

class ListAuthors extends ListRecords
{
    protected static string $resource = AuthorResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
            Action::make('generateAuthor')
                ->label('Generar Autor')
                ->icon('heroicon-o-sparkles')
                ->modalHeading('Generar Autor con IA')
                ->form([
                    Forms\Components\TextInput::make('name')
                        ->label('Nombre del autor')
                        ->placeholder('Ej. Laura Flores')
                        ->required(),
                ])
                ->action(function (array $data) {
                    /** @var AuthorGeneratorService $service */
                    $service = app(AuthorGeneratorService::class);
                    $author = $service->generate($data['name']);

                    Notification::make()
                        ->title('Autor generado correctamente')
                        ->success()
                        ->send();

                    return redirect(AuthorResource::getUrl('edit', ['record' => $author]));
                }),
        ];
    }
}
