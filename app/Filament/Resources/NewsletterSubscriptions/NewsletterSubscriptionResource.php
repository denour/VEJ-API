<?php

namespace App\Filament\Resources\NewsletterSubscriptions;

use App\Filament\Resources\NewsletterSubscriptions\Pages\CreateNewsletterSubscription;
use App\Filament\Resources\NewsletterSubscriptions\Pages\EditNewsletterSubscription;
use App\Filament\Resources\NewsletterSubscriptions\Pages\ListNewsletterSubscriptions;
use App\Filament\Resources\NewsletterSubscriptions\Schemas\NewsletterSubscriptionForm;
use App\Filament\Resources\NewsletterSubscriptions\Tables\NewsletterSubscriptionsTable;
use App\Models\NewsletterSubscription;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class NewsletterSubscriptionResource extends Resource
{
    protected static ?string $model = NewsletterSubscription::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::Envelope;

    protected static ?string $modelLabel = 'Newsletter Subscription';

    protected static ?string $pluralModelLabel = 'Newsletter Subscriptions';

    public static function form(Schema $schema): Schema
    {
        return NewsletterSubscriptionForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return NewsletterSubscriptionsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListNewsletterSubscriptions::route('/'),
            'create' => CreateNewsletterSubscription::route('/create'),
            'edit' => EditNewsletterSubscription::route('/{record}/edit'),
        ];
    }
}
