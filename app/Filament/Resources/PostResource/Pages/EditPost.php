<?php

namespace App\Filament\Resources\PostResource\Pages;

use App\Filament\Resources\PostResource;
use App\Services\AI\PostGeneratorService;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditPost extends EditRecord
{
    protected static string $resource = PostResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('generateContent')
                ->label('Generar Contenido con IA')
                ->icon('heroicon-o-sparkles')
                ->form([
                    TextInput::make('topic')
                        ->label('Tema del Post')
                        ->placeholder('Ej: Cuidados de plantas suculentas')
                        ->helperText('Deja vacío para generar un tema aleatorio'),
                    Select::make('length')
                        ->label('Longitud del Contenido')
                        ->options([
                            'short' => 'Corto (4-5 bloques)',
                            'medium' => 'Medio (5-8 bloques)',
                            'long' => 'Largo (8-10 bloques)',
                        ])
                        ->default('medium')
                        ->required(),
                ])
                ->requiresConfirmation()
                ->modalDescription('Esto generará nuevo contenido para el post basándose en el autor asignado y sus características.')
                ->action(function (array $data, PostGeneratorService $service): void {
                    $post = $this->record;

                    if (! $post->author) {
                        Notification::make()
                            ->title('Error')
                            ->body('El post debe tener un autor asignado.')
                            ->danger()
                            ->send();

                        return;
                    }

                    try {
                        $options = [
                            'length' => $data['length'] ?? 'medium',
                        ];

                        // Generate post data without creating a temporary post
                        $generatedData = $service->generatePostData(
                            $post->author,
                            $data['topic'] ?? null,
                            $options
                        );

                        // Update the existing post with the generated content
                        $post->update([
                            'title' => $generatedData['title'],
                            'slug' => \Illuminate\Support\Str::slug($generatedData['title']),
                            'excerpt' => $generatedData['excerpt'],
                            'content' => $generatedData['content'],
                            'list' => $generatedData['list'],
                            'category' => $generatedData['category'],
                            'tags' => $generatedData['tags'],
                            'reading_time' => $generatedData['reading_time'],
                        ]);

                        // Generate images for the original post (not a temporary one)
                        $service->generateImagesForPost($post, $generatedData['structure']);

                        Notification::make()
                            ->title('Contenido generado')
                            ->body('El contenido del post se generó exitosamente. Las imágenes se están generando en segundo plano.')
                            ->success()
                            ->send();

                        $this->redirect($this->getResource()::getUrl('edit', ['record' => $post]));
                    } catch (\Exception $e) {
                        Notification::make()
                            ->title('Error al generar contenido')
                            ->body($e->getMessage())
                            ->danger()
                            ->send();
                    }
                }),
        ];
    }
}
