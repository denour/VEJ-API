<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ProductResource\Pages;
use App\Models\Product;
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

class ProductResource extends Resource
{
    protected static ?string $model = Product::class;

    protected static BackedEnum|string|null $navigationIcon = Heroicon::ShoppingBag;

    protected static ?string $modelLabel = 'Producto';

    protected static ?string $pluralModelLabel = 'Productos';

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([
            Grid::make(12)->schema([
                Section::make('Información básica')->columnSpan(8)->schema([
                    Forms\Components\TextInput::make('name')->label('Nombre')->required(),
                    Forms\Components\TextInput::make('scientific_name')->label('Nombre científico'),
                    Forms\Components\FileUpload::make('image')->label('Imagen principal')->image()->disk('s3')->visibility('public')->storeFileNamesIn('image_filename'),
                    Forms\Components\FileUpload::make('images')->label('Galería')
                        ->image()->multiple()->disk('s3')->visibility('public')->storeFileNamesIn('images_filenames'),
                ]),
                Section::make('Detalles botánicos')->columnSpan(4)->schema([
                    Forms\Components\Select::make('care_level')->label('Nivel de cuidado')
                        ->options(['easy' => 'Fácil', 'medium' => 'Medio', 'hard' => 'Difícil']),
                    Forms\Components\Select::make('sunlight')->label('Luz')
                        ->options(['low' => 'Baja', 'medium' => 'Media', 'high' => 'Alta']),
                    Forms\Components\Select::make('watering')->label('Riego')
                        ->options(['low' => 'Bajo', 'medium' => 'Medio', 'high' => 'Alto']),
                    Forms\Components\Select::make('condition')->label('Condición')
                        ->options(['seedling' => 'Plántula', 'young' => 'Joven', 'mature' => 'Madura']),
                    Forms\Components\Select::make('size')->label('Tamaño')
                        ->options(['small' => 'Chico', 'medium' => 'Mediano', 'large' => 'Grande', 'xl' => 'XL']),
                    Forms\Components\Toggle::make('is_rare')->label('Es rara')->default(false),
                ]),
                Section::make('Transacción y stock')->columnSpan(12)->schema([
                    Grid::make(12)->schema([
                        Forms\Components\Select::make('type')->label('Tipo')->options([
                            'sale' => 'Venta',
                            'trade' => 'Intercambio',
                            'free' => 'Gratis',
                        ])->required()->columnSpan(2),
                        Forms\Components\TextInput::make('price')->label('Precio')->numeric()->prefix('$')->columnSpan(2),
                        Forms\Components\TextInput::make('currency')->label('Moneda')->default('MXN')->maxLength(3)->columnSpan(2),
                        Forms\Components\TextInput::make('rating')->numeric()->minValue(0)->maxValue(5)->step('0.1')->columnSpan(2),
                        Forms\Components\TextInput::make('reviews')->numeric()->minValue(0)->columnSpan(2),
                        Forms\Components\Toggle::make('in_stock')->label('En stock')->default(true)->columnSpan(2),
                        Forms\Components\TextInput::make('quantity')->numeric()->minValue(0)->columnSpan(2),
                    ]),
                ]),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\ImageColumn::make('image')->label('Imagen')->circular(),
                Tables\Columns\TextColumn::make('name')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('type')->badge()->sortable(),
                Tables\Columns\TextColumn::make('price')->money(fn ($record) => $record->currency)->sortable(),
                Tables\Columns\ToggleColumn::make('in_stock')->label('Stock'),
                Tables\Columns\TextColumn::make('updated_at')->since()->label('Actualizado'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('type')->options([
                    'sale' => 'Venta',
                    'trade' => 'Intercambio',
                    'free' => 'Gratis',
                ]),
                Tables\Filters\TernaryFilter::make('in_stock')->label('En stock'),
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
            'index' => Pages\ListProducts::route('/'),
            'create' => Pages\CreateProduct::route('/create'),
            'edit' => Pages\EditProduct::route('/{record}/edit'),
        ];
    }
}
