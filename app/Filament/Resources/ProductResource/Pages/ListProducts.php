<?php

namespace App\Filament\Resources\ProductResource\Pages;

use App\Filament\Resources\ProductResource;
use App\Services\AI\ProductGeneratorService;
use Filament\Actions;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;

class ListProducts extends ListRecords
{
    protected static string $resource = ProductResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
            Actions\Action::make('generateProduct')
                ->label('Generar Producto')
                ->icon('heroicon-o-sparkles')
                ->modalHeading('Generar Producto con IA')
                ->form([
                    Forms\Components\TextInput::make('title')
                        ->label('Título del producto')
                        ->placeholder('Ej. Monstera deliciosa en maceta de barro')
                        ->required(),
                ])
                ->action(function (array $data) {
                    /** @var ProductGeneratorService $service */
                    $service = app(ProductGeneratorService::class);
                    $product = $service->generate($data['title']);

                    Notification::make()
                        ->title('Producto generado correctamente')
                        ->success()
                        ->send();

                    return redirect(ProductResource::getUrl('edit', ['record' => $product]));
                }),
        ];
    }
}
