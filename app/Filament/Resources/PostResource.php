<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PostResource\Pages;
use App\Models\Post;
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

class PostResource extends Resource
{
    protected static ?string $model = Post::class;

    protected static BackedEnum|string|null $navigationIcon = Heroicon::DocumentText;

    protected static ?string $modelLabel = 'Post';

    protected static ?string $pluralModelLabel = 'Posts';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Grid::make(12)->schema([
                    Section::make('Contenido')
                        ->columnSpan(12)
                        ->schema([
                            Forms\Components\TextInput::make('title')->label('Título')->required(),
                            Forms\Components\TextInput::make('slug')->required()->unique(ignoreRecord: true),
                            Forms\Components\Textarea::make('excerpt')->rows(3),
                            Forms\Components\Repeater::make('content')
                                ->label('Bloques de contenido')
                                ->schema([
                                    Forms\Components\Select::make('type')
                                        ->options([
                                            'paragraph' => 'Párrafo',
                                            'heading' => 'Encabezado',
                                            'image' => 'Imagen',
                                            'list' => 'Lista',
                                            'quote' => 'Cita',
                                        ])->required(),
                                    Forms\Components\KeyValue::make('data')->keyLabel('Clave')->valueLabel('Valor'),
                                ])
                                ->collapsed()
                                ->reorderable(true),
                            Forms\Components\Repeater::make('list')
                                ->label('Tabla de contenido')
                                ->schema([
                                    Forms\Components\TextInput::make('id')->required(),
                                    Forms\Components\TextInput::make('text')->required(),
                                ])->collapsed(),
                        ]),
                    Section::make('Metadatos')
                        ->columnSpan(12)
                        ->schema([
                            Forms\Components\TextInput::make('category')->required(),
                            Forms\Components\TagsInput::make('tags')->label('Tags'),
                            Forms\Components\Select::make('author_id')
                                ->label('Autor')
                                ->relationship('author', 'name')
                                ->required()
                                ->searchable()
                                ->preload()
                                ->createOptionForm([
                                    Forms\Components\TextInput::make('name')->required(),
                                    Forms\Components\FileUpload::make('image')
                                        ->image()->disk('s3')->visibility('public')->storeFileNamesIn('image_filename'),
                                    Forms\Components\Textarea::make('description')->rows(3),
                                ]),
                            Forms\Components\FileUpload::make('cover_image')->label('Imagen de portada')
                                ->image()->disk('s3')->visibility('public')->storeFileNamesIn('cover_image_filename'),
                            Forms\Components\Toggle::make('featured')->label('Destacado')->default(false),
                            Forms\Components\Select::make('status')->options([
                                'draft' => 'Borrador',
                                'published' => 'Publicado',
                            ])->required()->default('draft'),
                            Forms\Components\DateTimePicker::make('published_at')->label('Fecha publicación'),
                            Forms\Components\TextInput::make('reading_time')->numeric()->minValue(0),
                        ]),
                ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('title')->label('Título')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('category')->searchable()->sortable(),
                Tables\Columns\ToggleColumn::make('featured')->label('Destacado'),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state) => match ($state) {
                        'published' => 'success',
                        default => 'gray',
                    })
                    ->sortable(),
                Tables\Columns\TextColumn::make('published_at')->dateTime()->since()->sortable(),
                Tables\Columns\TextColumn::make('updated_at')->since()->label('Actualizado'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')->options([
                    'draft' => 'Borrador',
                    'published' => 'Publicado',
                ]),
                Tables\Filters\TernaryFilter::make('featured')->label('Destacado'),
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
            'index' => Pages\ListPosts::route('/'),
            'create' => Pages\CreatePost::route('/create'),
            'edit' => Pages\EditPost::route('/{record}/edit'),
        ];
    }
}
