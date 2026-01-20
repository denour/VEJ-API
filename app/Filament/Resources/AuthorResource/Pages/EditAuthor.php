<?php

namespace App\Filament\Resources\AuthorResource\Pages;

use App\Filament\Resources\AuthorResource;
use App\Services\AI\PersonaPreviewService;
use App\Services\AI\VoiceBibleGeneratorService;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditAuthor extends EditRecord
{
    protected static string $resource = AuthorResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('generate_voice_bible')
                ->label('Generate Voice Bible')
                ->icon('heroicon-o-document-text')
                ->color('success')
                ->requiresConfirmation()
                ->modalHeading('Generate Voice Bible')
                ->modalDescription('This will create a 300-word style guide based on the current persona settings. Any existing Voice Bible will be replaced.')
                ->modalSubmitActionLabel('Generate')
                ->action(function () {
                    $persona = $this->record;

                    // Validate minimum requirements
                    if (empty($persona->background_story)) {
                        Notification::make()
                            ->warning()
                            ->title('Missing information')
                            ->body('Please add a background story first.')
                            ->send();

                        return;
                    }

                    try {
                        $service = app(VoiceBibleGeneratorService::class);
                        $voiceBible = $service->generate($persona);

                        $persona->update(['voice_bible' => $voiceBible]);

                        Notification::make()
                            ->success()
                            ->title('Voice Bible generated')
                            ->body('300-word style guide created successfully.')
                            ->send();

                        $this->refreshFormData(['voice_bible']);
                    } catch (\Exception $e) {
                        Notification::make()
                            ->danger()
                            ->title('Generation failed')
                            ->body($e->getMessage())
                            ->send();
                    }
                }),

            Actions\Action::make('preview_voice')
                ->label('Preview Voice')
                ->icon('heroicon-o-eye')
                ->color('info')
                ->form([
                    \Filament\Forms\Components\TextInput::make('topic')
                        ->label('Sample Topic')
                        ->default('caring for indoor plants during winter')
                        ->required(),
                ])
                ->action(function (array $data) {
                    try {
                        $service = app(PersonaPreviewService::class);
                        $sample = $service->generateSampleParagraph($this->record, $data['topic']);

                        $this->record->update(['sample_paragraph' => $sample]);

                        Notification::make()
                            ->success()
                            ->title('Preview generated')
                            ->body($sample)
                            ->persistent()
                            ->send();

                        $this->refreshFormData(['sample_paragraph']);
                    } catch (\Exception $e) {
                        Notification::make()
                            ->danger()
                            ->title('Preview failed')
                            ->body($e->getMessage())
                            ->send();
                    }
                }),

            Actions\DeleteAction::make()
                ->icon('heroicon-o-trash'),
        ];
    }
}
