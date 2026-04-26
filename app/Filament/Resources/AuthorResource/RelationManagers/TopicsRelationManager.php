<?php

namespace App\Filament\Resources\AuthorResource\RelationManagers;

use App\Services\AI\TopicGeneratorService;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class TopicsRelationManager extends RelationManager
{
    protected static string $relationship = 'topics';

    protected static ?string $title = 'Temas';

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('topic')
                    ->label('Tema')
                    ->searchable()
                    ->limit(60),
                TextColumn::make('category')
                    ->label('Categoría')
                    ->badge(),
                TextColumn::make('used_at')
                    ->label('Estado')
                    ->formatStateUsing(fn ($state) => $state ? 'Usado' : 'Pendiente')
                    ->badge()
                    ->color(fn ($state) => $state ? 'gray' : 'success'),
                TextColumn::make('post.title')
                    ->label('Post')
                    ->limit(40)
                    ->placeholder('-'),
                TextColumn::make('created_at')
                    ->label('Creado')
                    ->dateTime('d/m/Y')
                    ->sortable(),
            ])
            ->defaultSort('used_at', 'asc')
            ->headerActions([
                Action::make('generateTopics')
                    ->label('Generar Temas con AI')
                    ->action(function (): void {
                        $author = $this->getOwnerRecord();
                        $generator = app(TopicGeneratorService::class);
                        $topics = $generator->generateTopics($author, 10);

                        Notification::make()
                            ->title("Se generaron {$topics->count()} temas")
                            ->success()
                            ->send();
                    }),
            ])
            ->actions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->bulkActions([
                DeleteBulkAction::make(),
            ]);
    }

    public function form(Schema $form): Schema
    {
        return $form->components([
            TextInput::make('topic')
                ->label('Tema')
                ->required()
                ->maxLength(255),
            Select::make('category')
                ->label('Categoría')
                ->options([
                    'Cuidado' => 'Cuidado',
                    'Identificación' => 'Identificación',
                    'Decoración' => 'Decoración',
                    'Herramientas' => 'Herramientas',
                    'Consejos' => 'Consejos',
                ]),
        ]);
    }
}
