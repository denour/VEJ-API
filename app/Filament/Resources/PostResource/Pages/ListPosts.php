<?php

namespace App\Filament\Resources\PostResource\Pages;

use App\Filament\Resources\PostResource;
use App\Models\Author;
use App\Services\AI\PostGeneratorService;
use Filament\Actions;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;

class ListPosts extends ListRecords
{
    protected static string $resource = PostResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
            Actions\Action::make('generatePost')
                ->label('Generar Post')
                ->icon('heroicon-o-sparkles')
                ->modalHeading('Generar Post con IA')
                ->form([
                    Forms\Components\Select::make('author_id')
                        ->label('Autor')
                        ->options(fn () => Author::query()->orderBy('name')->pluck('name', 'id'))
                        ->searchable()
                        ->required(),
                    Forms\Components\TextInput::make('topic')
                        ->label('Título o tema del post')
                        ->placeholder('Ej. Guía práctica para cuidar suculentas en interiores')
                        ->required(),
                ])
                ->action(function (array $data) {
                    /** @var PostGeneratorService $service */
                    $service = app(PostGeneratorService::class);
                    $author = Author::findOrFail($data['author_id']);
                    $post = $service->generatePost($author, $data['topic']);

                    Notification::make()
                        ->title('Post generado correctamente')
                        ->success()
                        ->send();

                    return redirect(PostResource::getUrl('edit', ['record' => $post]));
                }),
        ];
    }
}
