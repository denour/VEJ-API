<?php

namespace App\Filament\Resources\PostResource\Pages;

use App\Filament\Resources\PostResource;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;

class CreatePost extends CreateRecord
{
    protected static string $resource = PostResource::class;

    protected function afterCreate(): void
    {
        $needsImages = false;

        // Check if cover image was not uploaded
        if (empty($this->record->cover_image)) {
            $needsImages = true;
        }

        // Check if any content blocks need images
        if (! empty($this->record->content) && is_array($this->record->content)) {
            foreach ($this->record->content as $block) {
                if (
                    ($block['type'] ?? null) === 'image'
                    && empty($block['data']['url'] ?? null)
                    && ! empty($block['data']['alt'] ?? null)
                ) {
                    $needsImages = true;
                    break;
                }
            }
        }

        if ($needsImages) {
            Notification::make()
                ->title('Post creado')
                ->body('Las imágenes se están generando en segundo plano. Recibirás una notificación cuando estén listas.')
                ->success()
                ->send();
        }
    }
}
