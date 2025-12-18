<?php

namespace App\Filament\Resources;

use App\Filament\Resources\SettingResource\Pages;
use App\Models\Setting;
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

class SettingResource extends Resource
{
    protected static ?string $model = Setting::class;

    protected static BackedEnum|string|null $navigationIcon = Heroicon::Cog6Tooth;

    protected static ?string $modelLabel = 'Setting';

    protected static ?string $pluralModelLabel = 'Settings';

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([
            Grid::make(12)->schema([
                Section::make('General')->columnSpan(8)->schema([
                    Forms\Components\TextInput::make('key')
                        ->label('Llave')
                        ->unique(ignoreRecord: true)
                        ->required(),
                    Forms\Components\Select::make('type')
                        ->label('Tipo')
                        ->required()
                        ->options([
                            'string' => 'String',
                            'boolean' => 'Boolean',
                            'integer' => 'Integer',
                            'float' => 'Float',
                            'array' => 'Array',
                        ])
                        ->native(false),
                    Forms\Components\TextInput::make('typed_value')
                        ->label('Valor')
                        ->helperText('Para Array, ingresa JSON válido; para Boolean usa true/false')
                        ->reactive()
                        ->dehydrateStateUsing(function ($state, callable $get) {
                            $type = $get('type') ?: 'string';

                            return match ($type) {
                                'boolean' => ['value' => filter_var($state, FILTER_VALIDATE_BOOL)],
                                'integer' => ['value' => (int) $state],
                                'float' => ['value' => (float) $state],
                                'array' => ['value' => static::decodeArray($state)],
                                default => ['value' => (string) $state],
                            };
                        })
                        ->afterStateHydrated(function (Forms\Components\TextInput $component, $state, $record): void {
                            if (! $record) {
                                return;
                            }
                            $component->state(match ($record->type) {
                                'boolean' => isset($record->value['value']) ? ($record->value['value'] ? 'true' : 'false') : '',
                                'integer', 'float' => (string) ($record->value['value'] ?? ''),
                                'array' => json_encode($record->value['value'] ?? []),
                                default => (string) ($record->value['value'] ?? ''),
                            });
                        })
                        ->dehydrated(true)
                        ->dehydratedWhenHidden(false)
                        ->required(),
                ]),
                Section::make('Metadatos')->columnSpan(4)->schema([
                    Forms\Components\Placeholder::make('updated_at')->label('Actualizado')->content(fn (?AppSetting $record) => $record?->updated_at?->diffForHumans() ?? '-'),
                ]),
            ]),
        ]);
    }

    protected static function decodeArray(?string $input): array
    {
        if (! $input) {
            return [];
        }
        $decoded = json_decode($input, true);

        return is_array($decoded) ? $decoded : [];
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('key')->label('Llave')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('type')->label('Tipo')->badge()->sortable(),
                Tables\Columns\TextColumn::make('value')
                    ->label('Valor')
                    ->formatStateUsing(function (array|string|null $state): string {
                        if (is_array($state)) {
                            return json_encode($state['value'] ?? null);
                        }

                        return (string) $state;
                    })
                    ->wrap(),
                Tables\Columns\TextColumn::make('updated_at')->since()->label('Actualizado'),
            ])
            ->filters([])
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
            'index' => Pages\ListSettings::route('/'),
            'create' => Pages\CreateSetting::route('/create'),
            'edit' => Pages\EditSetting::route('/{record}/edit'),
        ];
    }
}
