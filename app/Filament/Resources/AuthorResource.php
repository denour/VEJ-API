<?php

namespace App\Filament\Resources;

use App\Enums\Formality;
use App\Enums\SentenceStyle;
use App\Enums\VocabularyLevel;
use App\Enums\WritingTone;
use App\Filament\Resources\AuthorResource\Pages;
use App\Filament\Resources\AuthorResource\RelationManagers;
use App\Models\Author;
use App\Services\AI\PersonaFieldAssistantService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables;
use Filament\Tables\Table;

class AuthorResource extends Resource
{
    protected static ?string $model = Author::class;

    protected static BackedEnum|string|null $navigationIcon = Heroicon::UserCircle;

    protected static ?string $modelLabel = 'Author';

    protected static ?string $pluralModelLabel = 'Authors';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Tabs::make('Tabs')
                    ->tabs([
                        Tabs\Tab::make('Información Básica')
                            ->schema([
                                Section::make('Información Básica')
                                    ->schema([
                                        Grid::make(2)->schema([
                                            Forms\Components\TextInput::make('name')
                                                ->label('Nombre')
                                                ->required()
                                                ->unique(ignoreRecord: true)
                                                ->live(onBlur: true)
                                                ->afterStateUpdated(fn ($state, callable $set) => $set('slug', (string) str($state)->slug()))
                                                ->suffixAction(
                                                    static::createAiAssistAction('name')
                                                ),

                                            Forms\Components\TextInput::make('slug')
                                                ->label('Slug')
                                                ->required()
                                                ->unique(ignoreRecord: true)
                                                ->live(onBlur: true)
                                                ->afterStateUpdated(fn ($state, callable $set) => $set('slug', (string) str($state)->slug())),

                                            Forms\Components\Toggle::make('is_active')
                                                ->label('Activo')
                                                ->default(true),

                                            Forms\Components\TextInput::make('avatar_url')
                                                ->label('Imagen del Autor')
                                                ->url()
                                                ->placeholder('https://...')
                                                ->hiddenOn('create')
                                                ->suffixAction(
                                                    static::createAiAssistAction('avatar_url')
                                                ),
                                        ]),
                                    ]),
                            ]),

                        Tabs\Tab::make('Persona')
                            ->schema([
                                Section::make('Historia e Identidad')
                                    ->description('¿Quién es este autor? ¿Qué formó su perspectiva?')
                                    ->schema([
                                        Forms\Components\Textarea::make('background_story')
                                            ->label('Historia de Fondo')
                                            ->rows(5)
                                            ->columnSpanFull()
                                            ->placeholder('Describe quién es este autor, su historia, qué lo hace único...')
                                            ->hintAction(
                                                static::createAiAssistAction('background_story')
                                            ),

                                        Forms\Components\TagsInput::make('personality_traits')
                                            ->label('Rasgos de Personalidad')
                                            ->placeholder('Agrega un rasgo y presiona Enter')
                                            ->hintAction(
                                                static::createAiAssistAction('personality_traits')
                                            ),

                                        Forms\Components\TagsInput::make('expertise_areas')
                                            ->label('Áreas de Experiencia')
                                            ->placeholder('Agrega un área de experiencia y presiona Enter')
                                            ->hintAction(
                                                static::createAiAssistAction('expertise_areas')
                                            ),
                                    ])
                                    ->columns(2),

                                Section::make('Configuración de Voz')
                                    ->description('¿Cómo escribe este autor?')
                                    ->schema([
                                        Forms\Components\Select::make('sentence_style')
                                            ->label('Estilo de Oración')
                                            ->options(SentenceStyle::class)
                                            ->default(SentenceStyle::Varied->value)
                                            ->selectablePlaceholder(false),

                                        Forms\Components\Select::make('vocabulary_level')
                                            ->label('Nivel de Vocabulario')
                                            ->options(VocabularyLevel::class)
                                            ->default(VocabularyLevel::Conversational->value)
                                            ->selectablePlaceholder(false),

                                        Forms\Components\Select::make('tone')
                                            ->label('Tono de Escritura')
                                            ->options(WritingTone::class)
                                            ->default(WritingTone::Warm->value)
                                            ->selectablePlaceholder(false),

                                        Forms\Components\Select::make('formality')
                                            ->label('Nivel de Formalidad')
                                            ->options(Formality::class)
                                            ->default(Formality::Balanced->value)
                                            ->selectablePlaceholder(false),
                                    ])
                                    ->columns(4),

                                Section::make('Elementos Característicos')
                                    ->description('¿Qué hace reconocible a este autor?')
                                    ->schema([
                                        Forms\Components\TagsInput::make('catchphrases')
                                            ->label('Frases Características')
                                            ->placeholder('Agrega una frase')
                                            ->hintAction(
                                                static::createAiAssistAction('catchphrases')
                                            ),

                                        Forms\Components\TagsInput::make('quirks')
                                            ->label('Peculiaridades de Escritura')
                                            ->placeholder('Agrega una peculiaridad')
                                            ->hintAction(
                                                static::createAiAssistAction('quirks')
                                            ),

                                        Forms\Components\TagsInput::make('recurring_topics')
                                            ->label('Temas Recurrentes')
                                            ->placeholder('Agrega un tema')
                                            ->hintAction(
                                                static::createAiAssistAction('recurring_topics')
                                            ),

                                        Forms\Components\TagsInput::make('avoided_elements')
                                            ->label('Nunca Hace / Nunca Dice')
                                            ->placeholder('Agrega una restricción')
                                            ->hintAction(
                                                static::createAiAssistAction('avoided_elements')
                                            ),
                                    ])
                                    ->columns(2),
                            ]),

                        Tabs\Tab::make('Biblia de Voz')
                            ->schema([
                                Section::make('Biblia de Voz Generada')
                                    ->description('La guía de estilo completa para este autor (generada automáticamente)')
                                    ->schema([
                                        Forms\Components\Textarea::make('voice_bible')
                                            ->label('Biblia de Voz (300 palabras)')
                                            ->rows(15)
                                            ->disabled()
                                            ->columnSpanFull()
                                            ->placeholder('Genera la Biblia de Voz usando el botón de acción arriba'),

                                        Forms\Components\Textarea::make('sample_paragraph')
                                            ->label('Vista Previa de Ejemplo')
                                            ->rows(5)
                                            ->disabled()
                                            ->columnSpanFull()
                                            ->placeholder('Genera una vista previa usando el botón de acción arriba'),
                                    ]),
                            ])
                            ->visible(fn ($record) => $record !== null),
                    ])
                    ->columnSpanFull(),
            ]);
    }

    protected static function createAiAssistAction(string $fieldType): Action
    {
        return Action::make("ai_assist_{$fieldType}")
            ->label('Generate')
            ->icon('heroicon-o-sparkles')
            ->color('info')
            ->requiresConfirmation(false)
            ->action(function ($set, $get, $record) use ($fieldType) {
                // Si es avatar_url y no hay record, mostrar error
                if ($fieldType === 'avatar_url' && ! $record) {
                    Notification::make()
                        ->warning()
                        ->title('Guarda primero')
                        ->body('Debes guardar el autor antes de generar la imagen.')
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
                    $service = app(PersonaFieldAssistantService::class);

                    // Get existing form data for context
                    $tone = $get('tone');
                    $existingData = [
                        'name' => $get('name'),
                        'tone' => $tone instanceof \BackedEnum ? $tone->value : $tone,
                        'background_story' => $get('background_story'),
                        'author_id' => $record?->id,
                    ];

                    // Get default prompt based on field type
                    $prompt = static::getDefaultPromptForField($fieldType, $existingData);

                    $result = $service->generateFieldSuggestion(
                        $fieldType,
                        $prompt,
                        $existingData
                    );

                    if ($result['success']) {
                        $set($fieldType, $result['value']);

                        // Si se generó un nombre, generar también el slug y avatar
                        if ($fieldType === 'name') {
                            $name = $result['value'];
                            $set('slug', str($name)->slug());

                            // Generar avatar/thumbnail automáticamente solo si el autor ya existe
                            if ($record) {
                                $avatarPrompt = 'Genera una URL de imagen de retrato/thumbnail realista para un autor de blog de jardinería. El nombre del autor es: '.$name;
                                $avatarResult = $service->generateFieldSuggestion(
                                    'avatar_url',
                                    $avatarPrompt,
                                    ['name' => $name, 'author_id' => $record->id]
                                );

                                if ($avatarResult['success']) {
                                    $set('avatar_url', $avatarResult['value']);
                                }
                            }
                        }

                        Notification::make()
                            ->success()
                            ->title('¡Generado!')
                            ->body('Revisa y edita según necesites.')
                            ->send();
                    } else {
                        Notification::make()
                            ->danger()
                            ->title('Error')
                            ->body($result['error'] ?? 'Intenta de nuevo.')
                            ->send();
                    }
                } catch (\Exception $e) {
                    \Log::error('AI Assist Error', [
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
            'name' => 'Genera un nombre realista y memorable para un autor de blog de jardinería',
            'avatar_url' => 'Genera una URL de imagen de retrato/thumbnail realista para un autor de blog de jardinería. El nombre del autor es: '.($context['name'] ?? 'autor de jardinería'),
            'background_story' => 'Crea una historia de fondo convincente de 2-3 párrafos para este autor de jardinería',
            'personality_traits' => 'Lista 5-7 rasgos de personalidad para un escritor de contenido de jardinería',
            'expertise_areas' => 'Lista 4-6 áreas específicas de experiencia en jardinería',
            'catchphrases' => 'Genera 4-6 frases memorables características que este autor usaría',
            'quirks' => 'Lista 3-5 peculiaridades o hábitos únicos de escritura',
            'recurring_topics' => 'Lista 4-6 temas que este autor menciona frecuentemente',
            'avoided_elements' => 'Lista 3-5 cosas que este autor nunca escribiría',
            default => "Genera contenido apropiado para {$fieldType}",
        };
    }

    protected static function getPlaceholderForField(string $fieldType): string
    {
        return match ($fieldType) {
            'name' => 'e.g., "A warm, nurturing grandmother figure who loves tropical plants"',
            'background_story' => 'e.g., "A retired botanist who spent 30 years studying tropical plants in Costa Rica"',
            'personality_traits' => 'e.g., "Patient, methodical, loves sharing knowledge, slightly perfectionist"',
            'expertise_areas' => 'e.g., "Focus on tropical houseplants and propagation techniques"',
            'catchphrases' => 'e.g., "Friendly grandmother who uses old-fashioned expressions"',
            'quirks' => 'e.g., "Always relates plants to life lessons, uses seasonal metaphors"',
            'recurring_topics' => 'e.g., "Sustainability, organic methods, water conservation"',
            'avoided_elements' => 'e.g., "Never recommends chemical fertilizers, avoids complex jargon"',
            default => 'Describe what you want the AI to generate...',
        };
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\ImageColumn::make('avatar_url')
                    ->label('')
                    ->circular()
                    ->defaultImageUrl(fn ($record) => 'https://ui-avatars.com/api/?name='.urlencode($record->name)),

                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('tone')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'warm' => 'success',
                        'playful' => 'warning',
                        'authoritative' => 'primary',
                        'enthusiastic' => 'danger',
                        default => 'gray',
                    }),

                Tables\Columns\TextColumn::make('expertise_areas')
                    ->label('Expertise')
                    ->formatStateUsing(fn ($state) => is_array($state) ? implode(', ', array_slice($state, 0, 2)).'...' : $state)
                    ->wrap()
                    ->limit(50),

                Tables\Columns\IconColumn::make('voice_bible')
                    ->label('Voice Bible')
                    ->boolean()
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-x-circle')
                    ->trueColor('success')
                    ->falseColor('danger')
                    ->getStateUsing(fn ($record) => filled($record->voice_bible)),

                Tables\Columns\TextColumn::make('generation_stats.posts_generated')
                    ->label('Posts')
                    ->default(0)
                    ->sortable(),

                Tables\Columns\ToggleColumn::make('is_active')
                    ->label('Active'),

                Tables\Columns\TextColumn::make('updated_at')
                    ->label('Updated')
                    ->dateTime()
                    ->sortable()
                    ->since(),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Active')
                    ->placeholder('All')
                    ->trueLabel('Active only')
                    ->falseLabel('Inactive only'),

                Tables\Filters\Filter::make('has_voice_bible')
                    ->label('Has Voice Bible')
                    ->query(fn ($query) => $query->whereNotNull('voice_bible')),

                Tables\Filters\SelectFilter::make('tone')
                    ->options(WritingTone::class),
            ])
            ->actions([
                EditAction::make(),

                Action::make('quick_preview')
                    ->label('Preview')
                    ->icon('heroicon-o-eye')
                    ->color('info')
                    ->action(function ($record) {
                        $service = app(\App\Services\AI\PersonaPreviewService::class);

                        try {
                            $sample = $service->generateSampleParagraph($record);

                            Notification::make()
                                ->title("{$record->name} writes:")
                                ->body($sample)
                                ->success()
                                ->persistent()
                                ->send();
                        } catch (\Exception $e) {
                            Notification::make()
                                ->danger()
                                ->title('Preview failed')
                                ->body($e->getMessage())
                                ->send();
                        }
                    })
                    ->visible(fn ($record) => filled($record->voice_bible)),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('updated_at', 'desc');
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\TopicsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAuthors::route('/'),
            'create' => Pages\CreateAuthor::route('/create'),
            'edit' => Pages\EditAuthor::route('/{record}/edit'),
        ];
    }
}
