<?php

namespace App\Filament\Resources\WebhookEvents;

use App\Filament\Resources\WebhookEvents\Pages\ListWebhookEvents;
use App\Filament\Resources\WebhookEvents\Pages\ViewWebhookEvent;
use App\Models\PaymentEvent;
use BackedEnum;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

/**
 * Every webhook the gateways have sent, each stored once, and whether it
 * has been dealt with.
 */
class WebhookEventResource extends Resource
{
    protected static ?string $model = PaymentEvent::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSignal;

    protected static string|UnitEnum|null $navigationGroup = 'Money';

    protected static ?int $navigationSort = 2;

    protected static ?string $navigationLabel = 'Webhook log';

    protected static ?string $modelLabel = 'webhook';

    public static function canCreate(): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('gateway')->badge()->color('gray'),
                TextColumn::make('type')->searchable(),
                TextColumn::make('event_id')->label('Event')->searchable()->limit(28)->copyable(),
                TextColumn::make('created_at')->label('Received')->since()->sortable(),
                TextColumn::make('processed_at')->label('Dealt with')->since()->placeholder('Waiting')->sortable(),
            ])
            ->filters([
                SelectFilter::make('gateway')->options(['stripe' => 'Stripe', 'razorpay' => 'Razorpay', 'paypal' => 'PayPal', 'test' => 'Test']),
                TernaryFilter::make('processed')->label('Dealt with')
                    ->queries(
                        true: fn (Builder $query) => $query->whereNotNull('processed_at'),
                        false: fn (Builder $query) => $query->whereNull('processed_at'),
                    ),
            ])
            ->recordActions([ViewAction::make()]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('The webhook')->columns(3)->schema([
                TextEntry::make('gateway')->badge(),
                TextEntry::make('type'),
                TextEntry::make('event_id')->label('Event')->copyable(),
                TextEntry::make('created_at')->label('Received')->dateTime('j M Y, H:i:s'),
                TextEntry::make('processed_at')->label('Dealt with')->dateTime('j M Y, H:i:s')->placeholder('Waiting'),
            ]),
            Section::make('What it said')->schema([
                TextEntry::make('payload')->hiddenLabel()
                    ->state(fn (PaymentEvent $record) => (string) json_encode($record->payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE))
                    ->fontFamily('mono')
                    ->size('xs')
                    ->extraAttributes(['class' => 'whitespace-pre-wrap break-all'])
                    ->copyable(),
            ]),
        ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListWebhookEvents::route('/'),
            'view' => ViewWebhookEvent::route('/{record}'),
        ];
    }
}
