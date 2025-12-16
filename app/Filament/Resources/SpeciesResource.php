<?php

namespace App\Filament\Resources;

use App\Filament\Resources\SpeciesResource\Pages;
use App\Models\Species;
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
use Filament\Tables;
use Filament\Tables\Table;

class SpeciesResource extends Resource
{
    protected static ?string $model = Species::class;

    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-sparkles';

    protected static ?string $modelLabel = 'Especie';

    protected static ?string $pluralModelLabel = 'Especies';

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([
            Grid::make(12)->schema([
                Section::make('Identidad')->columnSpan(8)->schema([
                    Forms\Components\TextInput::make('common_name')->label('Nombre común')->required(),
                    Forms\Components\TextInput::make('scientific_name')->label('Nombre científico')->required(),
                    Forms\Components\TextInput::make('family')->label('Familia'),
                    Forms\Components\TextInput::make('origin')->label('Origen'),
                    Forms\Components\Textarea::make('description')->label('Descripción')->rows(6),
                    Forms\Components\FileUpload::make('image')->label('Imagen')->image()->disk('public')->visibility('public'),
                    Forms\Components\FileUpload::make('images')->label('Galería')->image()->multiple()->disk('public')->visibility('public'),
                ]),
                Section::make('Cuidado')->columnSpan(4)->schema([
                    Forms\Components\Select::make('care_level')->label('Nivel de cuidado')
                        ->options(['easy' => 'Fácil', 'medium' => 'Medio', 'hard' => 'Difícil']),
                    Forms\Components\Select::make('sunlight')->label('Luz')
                        ->options(['low' => 'Baja', 'medium' => 'Media', 'high' => 'Alta']),
                    Forms\Components\Select::make('watering')->label('Riego')
                        ->options(['low' => 'Bajo', 'medium' => 'Medio', 'high' => 'Alto']),
                    Forms\Components\Select::make('toxicity')->label('Toxicidad')
                        ->options(['none' => 'Ninguna', 'pets' => 'Mascotas', 'humans' => 'Humanos', 'both' => 'Ambos']),
                    Forms\Components\Select::make('growth_rate')->label('Crecimiento')
                        ->options(['slow' => 'Lento', 'medium' => 'Medio', 'fast' => 'Rápido']),
                    Forms\Components\TextInput::make('max_height_cm')->label('Altura máx (cm)')->numeric()->minValue(0),
                ]),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('common_name')->label('Nombre común')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('scientific_name')->label('Nombre científico')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('care_level')->label('Cuidado')->badge(),
                Tables\Columns\TextColumn::make('sunlight')->label('Luz')->badge(),
                Tables\Columns\TextColumn::make('watering')->label('Riego')->badge(),
                Tables\Columns\TextColumn::make('updated_at')->since()->label('Actualizado'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('care_level')->label('Cuidado')
                    ->options(['easy' => 'Fácil', 'medium' => 'Medio', 'hard' => 'Difícil']),
                Tables\Filters\SelectFilter::make('sunlight')->label('Luz')
                    ->options(['low' => 'Baja', 'medium' => 'Media', 'high' => 'Alta']),
                Tables\Filters\SelectFilter::make('watering')->label('Riego')
                    ->options(['low' => 'Bajo', 'medium' => 'Medio', 'high' => 'Alto']),
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
            'index' => Pages\ListSpecies::route('/'),
            'create' => Pages\CreateSpecies::route('/create'),
            'edit' => Pages\EditSpecies::route('/{record}/edit'),
        ];
    }
}
