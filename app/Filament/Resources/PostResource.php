<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PostResource\Pages;
use App\Models\Post;
use App\Services\AI\PostContentAssistantService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms;
use Filament\Notifications\Notification;
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

    protected static ?string $modelLabel = 'Artículo';

    protected static ?string $pluralModelLabel = 'Artículos';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Grid::make(12)->schema([
                    Section::make('Contenido')
                        ->columnSpan(12)
                        ->schema([
                            Forms\Components\TextInput::make('title')
                                ->label('Título')
                                ->required()
                                ->unique(ignoreRecord: true)
                                ->live(onBlur: true)
                                ->afterStateUpdated(fn ($state, callable $set) => $set('slug', str($state)->slug()))
                                ->suffixAction(
                                    static::createAiAssistAction('title')
                                ),

                            Forms\Components\TextInput::make('slug')
                                ->label('Slug')
                                ->required()
                                ->unique(ignoreRecord: true)
                                ->live(onBlur: true)
                                ->afterStateUpdated(fn ($state, callable $set) => $set('slug', str($state)->slug())),

                            Forms\Components\Textarea::make('excerpt')
                                ->label('Extracto')
                                ->rows(3)
                                ->hintAction(
                                    static::createAiAssistAction('excerpt')
                                ),

                            Forms\Components\Repeater::make('blocks')
                                ->label('Bloques de contenido')
                                ->relationship('blocks')
                                ->orderColumn('order')
                                ->reorderable(true)
                                ->collapsible()
                                ->collapsed()
                                ->itemLabel(fn (array $state): ?string => match ($state['type'] ?? null) {
                                    'paragraph' => '📝 Párrafo',
                                    'heading' => '📌 '.($state['title'] ?? 'Encabezado'),
                                    'image' => '🖼️ Imagen',
                                    'list' => '📋 Lista',
                                    'quote' => '💬 Cita',
                                    'code' => '💻 Código',
                                    'video' => '🎥 Video',
                                    default => 'Bloque',
                                })
                                ->schema([
                                    Forms\Components\Select::make('type')
                                        ->label('Tipo de bloque')
                                        ->options([
                                            'paragraph' => 'Párrafo',
                                            'heading' => 'Encabezado',
                                            'image' => 'Imagen',
                                            'list' => 'Lista',
                                            'quote' => 'Cita',
                                            'code' => 'Código',
                                            'video' => 'Video',
                                        ])
                                        ->required()
                                        ->live()
                                        ->afterStateUpdated(function ($state, $set) {
                                            // Reset fields when type changes
                                            $set('title', null);
                                            $set('content', null);
                                            $set('data', null);
                                        }),

                                    // Paragraph fields
                                    Forms\Components\Textarea::make('content')
                                        ->label('Contenido')
                                        ->rows(4)
                                        ->visible(fn ($get): bool => $get('type') === 'paragraph')
                                        ->hintAction(
                                            static::createAiAssistAction('paragraph')
                                        ),

                                    // Heading fields
                                    Forms\Components\TextInput::make('title')
                                        ->label('Título del encabezado')
                                        ->visible(fn ($get): bool => $get('type') === 'heading')
                                        ->suffixAction(
                                            static::createAiAssistAction('heading')
                                        ),
                                    Forms\Components\Select::make('data.level')
                                        ->label('Nivel')
                                        ->options([
                                            2 => 'H2',
                                            3 => 'H3',
                                            4 => 'H4',
                                        ])
                                        ->default(2)
                                        ->visible(fn ($get): bool => $get('type') === 'heading'),

                                    // Image fields
                                    Forms\Components\TextInput::make('data.url')
                                        ->label('URL de la imagen')
                                        ->url()
                                        ->visible(fn ($get): bool => $get('type') === 'image')
                                        ->helperText('Se generará automáticamente si usas el botón AI')
                                        ->suffixAction(
                                            static::createAiAssistAction('block_image')
                                        ),
                                    Forms\Components\TextInput::make('data.alt')
                                        ->label('Texto alternativo')
                                        ->visible(fn ($get): bool => $get('type') === 'image'),
                                    Forms\Components\TextInput::make('data.caption')
                                        ->label('Pie de imagen')
                                        ->visible(fn ($get): bool => $get('type') === 'image'),

                                    // List fields
                                    Forms\Components\TextInput::make('title')
                                        ->label('Título de la lista (opcional)')
                                        ->visible(fn ($get): bool => $get('type') === 'list'),
                                    Forms\Components\TagsInput::make('data.items')
                                        ->label('Items')
                                        ->placeholder('Escribe un item y presiona Enter')
                                        ->visible(fn ($get): bool => $get('type') === 'list')
                                        ->hintAction(
                                            static::createAiAssistAction('list_items')
                                        ),
                                    Forms\Components\Toggle::make('data.ordered')
                                        ->label('Lista ordenada')
                                        ->default(false)
                                        ->visible(fn ($get): bool => $get('type') === 'list'),

                                    // Quote fields
                                    Forms\Components\Textarea::make('content')
                                        ->label('Cita')
                                        ->rows(3)
                                        ->visible(fn ($get): bool => $get('type') === 'quote')
                                        ->hintAction(
                                            static::createAiAssistAction('quote_content')
                                        ),
                                    Forms\Components\TextInput::make('data.author')
                                        ->label('Autor')
                                        ->visible(fn ($get): bool => $get('type') === 'quote'),
                                    Forms\Components\TextInput::make('data.source')
                                        ->label('Fuente')
                                        ->visible(fn ($get): bool => $get('type') === 'quote'),

                                    // Code fields
                                    Forms\Components\TextInput::make('title')
                                        ->label('Título del bloque de código (opcional)')
                                        ->visible(fn ($get): bool => $get('type') === 'code'),
                                    Forms\Components\Textarea::make('content')
                                        ->label('Código')
                                        ->rows(6)
                                        ->visible(fn ($get): bool => $get('type') === 'code'),
                                    Forms\Components\Select::make('data.language')
                                        ->label('Lenguaje')
                                        ->options([
                                            'javascript' => 'JavaScript',
                                            'php' => 'PHP',
                                            'python' => 'Python',
                                            'html' => 'HTML',
                                            'css' => 'CSS',
                                            'bash' => 'Bash',
                                            'json' => 'JSON',
                                            'text' => 'Texto plano',
                                        ])
                                        ->default('text')
                                        ->visible(fn ($get): bool => $get('type') === 'code'),
                                    Forms\Components\TextInput::make('data.filename')
                                        ->label('Nombre del archivo (opcional)')
                                        ->visible(fn ($get): bool => $get('type') === 'code'),

                                    // Video fields
                                    Forms\Components\TextInput::make('title')
                                        ->label('Título del video (opcional)')
                                        ->visible(fn ($get): bool => $get('type') === 'video'),
                                    Forms\Components\TextInput::make('data.url')
                                        ->label('URL del video')
                                        ->url()
                                        ->required()
                                        ->visible(fn ($get): bool => $get('type') === 'video'),
                                    Forms\Components\Select::make('data.provider')
                                        ->label('Proveedor')
                                        ->options([
                                            'youtube' => 'YouTube',
                                            'vimeo' => 'Vimeo',
                                        ])
                                        ->default('youtube')
                                        ->visible(fn ($get): bool => $get('type') === 'video'),
                                    Forms\Components\TextInput::make('data.caption')
                                        ->label('Descripción')
                                        ->visible(fn ($get): bool => $get('type') === 'video'),
                                ])
                                ->defaultItems(0)
                                ->addActionLabel('Agregar bloque'),

                            Forms\Components\Placeholder::make('toc_info')
                                ->label('Tabla de contenidos')
                                ->content('La tabla de contenidos se genera automáticamente desde los encabezados.'),
                        ]),
                    Section::make('Metadatos')
                        ->columnSpan(12)
                        ->schema([
                            Forms\Components\TextInput::make('category')
                                ->label('Categoría')
                                ->required(),
                            Forms\Components\TagsInput::make('tags')
                                ->label('Tags')
                                ->hintAction(
                                    static::createAiAssistAction('tags')
                                ),
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
                            Forms\Components\TextInput::make('cover_image')
                                ->label('Imagen de portada')
                                ->url()
                                ->placeholder('https://... o genera con AI')
                                ->helperText('Se generará automáticamente si usas el botón AI')
                                ->suffixAction(
                                    static::createAiAssistAction('cover_image')
                                ),
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

    protected static function createAiAssistAction(string $fieldType): Action
    {
        return Action::make("ai_assist_{$fieldType}")
            ->label('Generate')
            ->icon('heroicon-o-sparkles')
            ->color('info')
            ->requiresConfirmation(false)
            ->action(function ($set, $get, $record) use ($fieldType) {
                // Check if we need a saved record for image generation
                if (in_array($fieldType, ['cover_image', 'block_image']) && ! $record) {
                    Notification::make()
                        ->warning()
                        ->title('Guarda primero')
                        ->body('Debes guardar el post antes de generar la imagen.')
                        ->send();

                    return;
                }

                // Show loading notification
                Notification::make()
                    ->info()
                    ->title('Generando...')
                    ->body('El AI está procesando tu solicitud.')
                    ->send();

                try {
                    $service = app(PostContentAssistantService::class);

                    // Get existing form data for context
                    // For fields inside repeater, $get() is already scoped to the repeater item
                    // For top-level fields, we need to use $get('../../field') to access them
                    $context = [
                        'title' => $get('../../title') ?: $get('../../../title'),
                        'category' => $get('../../category') ?: $get('../../../category'),
                        'excerpt' => $get('../../excerpt') ?: $get('../../../excerpt'),
                        'author_name' => $record?->author?->name,
                        'post_id' => $record?->id,
                    ];

                    // For block images, add block context
                    if ($fieldType === 'block_image') {
                        $context['block_title'] = $get('title');
                        $context['block_content'] = $get('content');
                        $context['block_type'] = $get('type');
                        // Try to get the block ID if it exists (for existing blocks)
                        $context['block_id'] = $get('id');
                    }

                    // Get default prompt based on field type
                    $prompt = static::getDefaultPromptForField($fieldType, $context);

                    $result = $service->generateFieldContent(
                        $fieldType,
                        $prompt,
                        $context
                    );

                    if ($result['success']) {
                        // Determine which field to set based on field type
                        $targetField = match ($fieldType) {
                            'list_items' => 'data.items',
                            'paragraph', 'quote_content' => 'content',
                            'heading' => 'title',
                            'block_image' => 'data.url',
                            default => $fieldType,
                        };

                        $set($targetField, $result['value']);

                        // Si se generó un título del post, generar también el slug
                        if ($fieldType === 'title') {
                            $title = $result['value'];
                            // Check if we're in the top-level form (post title) or inside repeater (heading title)
                            // If $get('../../title') returns null, we're at the top level
                            if ($get('../../title') === null) {
                                // We're at the top level, set slug directly
                                $set('slug', str($title)->slug());
                            }
                        }

                        $notificationBody = isset($result['pending']) && $result['pending']
                            ? 'La imagen se está generando. Se actualizará automáticamente cuando esté lista.'
                            : 'Revisa y edita según necesites.';

                        Notification::make()
                            ->success()
                            ->title('¡Generado!')
                            ->body($notificationBody)
                            ->send();
                    } else {
                        Notification::make()
                            ->danger()
                            ->title('Error')
                            ->body($result['error'] ?? 'Intenta de nuevo.')
                            ->send();
                    }
                } catch (\Exception $e) {
                    \Log::error('Post AI Assist Error', [
                        'field' => $fieldType,
                        'error' => $e->getMessage(),
                    ]);

                    Notification::make()
                        ->danger()
                        ->title('Error')
                        ->body($e->getMessage())
                        ->send();
                }
            });
    }

    protected static function getDefaultPromptForField(string $fieldType, array $context): string
    {
        return match ($fieldType) {
            'title' => 'Genera un título atractivo para un post de blog de jardinería sobre '.(! empty($context['category']) ? $context['category'] : 'jardinería'),
            'excerpt' => 'Genera un extracto atractivo para el post',
            'paragraph' => 'Genera un párrafo informativo sobre jardinería',
            'heading' => 'Genera un encabezado descriptivo para organizar el contenido',
            'list_items' => 'Lista 5-7 items informativos sobre el tema',
            'quote_content' => 'Genera una cita inspiradora relacionada con jardinería',
            'tags' => 'Genera 5-8 tags relevantes para este post de jardinería',
            'cover_image' => 'Imagen destacada para: '.(! empty($context['title']) ? $context['title'] : 'post de jardinería'),
            'block_image' => 'Imagen ilustrativa para: '.(! empty($context['block_content']) ? $context['block_content'] : ($context['block_title'] ?? 'contenido de jardinería')),
            default => "Genera contenido apropiado para {$fieldType}",
        };
    }
}
