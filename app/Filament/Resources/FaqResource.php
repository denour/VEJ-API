<?php

namespace App\Filament\Resources;

use App\Filament\Resources\FaqResource\Pages;
use App\Models\Faq;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables;
use Filament\Tables\Table;

class FaqResource extends Resource
{
    protected static ?string $model = Faq::class;

    protected static BackedEnum|string|null $navigationIcon = Heroicon::QuestionMarkCircle;

    protected static ?string $modelLabel = 'FAQ';

    protected static ?string $pluralModelLabel = 'FAQs';

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([
            Grid::make(12)->schema([
                Section::make('Contenido')->columnSpan(8)->schema([
                    Forms\Components\TextInput::make('question')->label('Pregunta')->required(),
                    Forms\Components\Textarea::make('answer')->label('Respuesta')->rows(6)->required(),
                ]),
                Section::make('Metadatos')->columnSpan(4)->schema([
                    Forms\Components\TextInput::make('category')->label('Categoría')->required(),
                ]),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('question')->label('Pregunta')->wrap()->searchable()->sortable(),
                Tables\Columns\TextColumn::make('category')->label('Categoría')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('updated_at')->since()->label('Actualizado'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('category')->label('Categoría')
                    ->options(fn () => Faq::query()->distinct()->pluck('category', 'category')->all()),
            ])
            ->deferFilters(false)
            ->actions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListFaqs::route('/'),
            'create' => Pages\CreateFaq::route('/create'),
            'edit' => Pages\EditFaq::route('/{record}/edit'),
        ];
    }
}
