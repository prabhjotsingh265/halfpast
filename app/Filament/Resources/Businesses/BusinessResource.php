<?php

namespace App\Filament\Resources\Businesses;

use App\Enums\BookingStatus;
use App\Filament\Resources\Businesses\Pages\ListBusinesses;
use App\Filament\Resources\Businesses\Pages\ViewBusiness;
use App\Mail\Money;
use App\Models\Business;
use App\Models\LedgerEntry;
use App\Models\Service;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use UnitEnum;

/**
 * Every business on the platform, real and demo.
 */
class BusinessResource extends Resource
{
    protected static ?string $model = Business::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingStorefront;

    protected static string|UnitEnum|null $navigationGroup = 'Businesses';

    protected static ?int $navigationSort = 1;

    protected static ?string $recordTitleAttribute = 'name';

    public static function canCreate(): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with('owners')->withCount('bookings'))
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('name')->searchable()->sortable()->description(fn (Business $record) => '/'.$record->slug),
                TextColumn::make('owner')
                    ->state(fn (Business $record) => $record->owners->first()?->email)
                    ->placeholder('No owner'),
                IconColumn::make('is_demo')->label('Demo')->boolean(),
                TextColumn::make('currency'),
                TextColumn::make('timezone')->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('bookings_count')->label('Bookings')->numeric()->sortable(),
                TextColumn::make('created_at')->label('Joined')->since()->sortable(),
            ])
            ->filters([
                TernaryFilter::make('is_demo')->label('Demo businesses'),
            ])
            ->recordActions([
                ViewAction::make(),
                Action::make('page')
                    ->label('Booking page')
                    ->icon(Heroicon::OutlinedArrowTopRightOnSquare)
                    ->url(fn (Business $record) => route('booking.show', ['business' => $record->slug]))
                    ->openUrlInNewTab(),
            ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('The business')->columns(3)->schema([
                TextEntry::make('name'),
                TextEntry::make('slug')->label('Booking page')->formatStateUsing(fn (string $state) => '/'.$state)
                    ->url(fn (Business $record) => route('booking.show', ['business' => $record->slug]), shouldOpenInNewTab: true),
                TextEntry::make('owner')->state(fn (Business $record) => $record->owners->pluck('email')->join(', ') ?: null)->placeholder('No owner'),
                TextEntry::make('timezone'),
                TextEntry::make('currency'),
                TextEntry::make('is_demo')->label('Demo')->formatStateUsing(fn (bool $state) => $state ? 'Yes, rebuilt every night' : 'No'),
                TextEntry::make('created_at')->label('Joined')->dateTime('j M Y, H:i'),
            ]),
            Section::make('Booking rules')->columns(3)->schema([
                TextEntry::make('slot_step_minutes')->label('Slot step')->suffix(' min'),
                TextEntry::make('min_notice_minutes')->label('Minimum notice')->suffix(' min'),
                TextEntry::make('booking_horizon_days')->label('Books ahead')->suffix(' days'),
                TextEntry::make('cancellation_window_hours')->label('Free cancellation until')->suffix(' h before'),
                TextEntry::make('hold_minutes')->label('Hold')->suffix(' min'),
                TextEntry::make('payment_gateways')->label('Payment methods on')->badge()->placeholder('None'),
            ]),
            Section::make('At a glance')->columns(4)->schema([
                TextEntry::make('team')->state(fn (Business $record) => $record->staff()->withoutGlobalScopes()->count()),
                TextEntry::make('services')->state(fn (Business $record) => Service::withoutGlobalScopes()->where('business_id', $record->id)->count()),
                TextEntry::make('confirmed')->label('Bookings to come')->state(fn (Business $record) => $record->bookings()->withoutGlobalScopes()->where('status', BookingStatus::Confirmed)->where('starts_at', '>', now())->count()),
                TextEntry::make('deposits')->label('Deposits kept')->state(fn (Business $record) => Money::format((int) LedgerEntry::withoutGlobalScopes()->where('business_id', $record->id)->sum('amount'), $record->currency)),
            ]),
        ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListBusinesses::route('/'),
            'view' => ViewBusiness::route('/{record}'),
        ];
    }
}
