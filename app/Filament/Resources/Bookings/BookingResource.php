<?php

namespace App\Filament\Resources\Bookings;

use App\Enums\BookingStatus;
use App\Filament\Resources\Bookings\Pages\ListBookings;
use App\Filament\Resources\Bookings\Pages\ViewBooking;
use App\Models\Booking;
use App\Support\Calendar\BookingHistory;
use BackedEnum;
use Carbon\CarbonImmutable;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

/**
 * Every booking on the platform, in its own business's time.
 */
class BookingResource extends Resource
{
    protected static ?string $model = Booking::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalendarDays;

    protected static string|UnitEnum|null $navigationGroup = 'Businesses';

    protected static ?int $navigationSort = 2;

    protected static ?string $recordTitleAttribute = 'code';

    public static function canCreate(): bool
    {
        return false;
    }

    /** "Tue 27 Oct, 14:30" where the business is. */
    public static function local(?CarbonImmutable $moment, Booking $booking): ?string
    {
        return $moment?->setTimezone($booking->business->timezone)->format('D j M Y, H:i');
    }

    public static function statusColour(BookingStatus $status): string
    {
        return match ($status) {
            BookingStatus::Confirmed => 'primary',
            BookingStatus::Completed => 'success',
            BookingStatus::NoShow, BookingStatus::Cancelled => 'danger',
            default => 'gray',
        };
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['business', 'staff', 'customer']))
            ->defaultSort('starts_at', 'desc')
            ->columns([
                TextColumn::make('code')->label('Reference')->formatStateUsing(fn (Booking $record) => $record->displayCode())->searchable(),
                TextColumn::make('business.name')->searchable(),
                TextColumn::make('service_name')->label('Service')->searchable(),
                TextColumn::make('staff.name')->label('With')->placeholder('Anyone'),
                TextColumn::make('customer.name')->label('Customer')->placeholder('Walk-in')->searchable(),
                TextColumn::make('starts_at')->label('When, there')->formatStateUsing(fn ($state, Booking $record) => self::local($state, $record))->sortable(),
                TextColumn::make('status')->badge()->formatStateUsing(fn (BookingStatus $state) => str($state->value)->replace('_', '-')->ucfirst())->color(fn (BookingStatus $state) => self::statusColour($state)),
                TextColumn::make('source')->badge()->color('gray'),
                TextColumn::make('price')->money(fn (Booking $record) => $record->currency, divideBy: 100)->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')->options(collect(BookingStatus::cases())->mapWithKeys(fn (BookingStatus $s) => [$s->value => str($s->value)->replace('_', '-')->ucfirst()->toString()])->all()),
                SelectFilter::make('business')->relationship('business', 'name')->searchable()->preload(),
                SelectFilter::make('source')->options(['online' => 'Online', 'manual' => 'By the business']),
                Filter::make('upcoming')->label('Still to come')->query(fn (Builder $query) => $query->where('starts_at', '>', now())),
            ])
            ->recordActions([ViewAction::make()]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('The booking')->columns(3)->schema([
                TextEntry::make('code')->label('Reference')->formatStateUsing(fn (Booking $record) => $record->displayCode())->copyable(),
                TextEntry::make('business.name'),
                TextEntry::make('status')->badge()->color(fn (BookingStatus $state) => self::statusColour($state)),
                TextEntry::make('service_name')->label('Service'),
                TextEntry::make('staff.name')->label('With')->placeholder('Anyone'),
                TextEntry::make('source'),
                TextEntry::make('starts_at')->label('Starts, there')->formatStateUsing(fn ($state, Booking $record) => self::local($state, $record)),
                TextEntry::make('ends_at')->label('Ends, there')->formatStateUsing(fn ($state, Booking $record) => self::local($state, $record)),
                TextEntry::make('business.timezone')->label('Time zone'),
                TextEntry::make('price')->money(fn (Booking $record) => $record->currency, divideBy: 100),
                TextEntry::make('deposit')->money(fn (Booking $record) => $record->currency, divideBy: 100),
                TextEntry::make('refund_due')->label('Refund due')->money(fn (Booking $record) => $record->currency, divideBy: 100),
            ]),
            Section::make('Customer')->columns(3)->schema([
                TextEntry::make('customer.name')->label('Name')->placeholder('Walk-in'),
                TextEntry::make('customer.email')->label('Email')->placeholder('None'),
                TextEntry::make('customer.phone')->label('Phone')->placeholder('None'),
                TextEntry::make('notes')->columnSpanFull()->placeholder('No notes'),
            ]),
            Section::make('History')->schema([
                TextEntry::make('history')->hiddenLabel()
                    ->state(fn (Booking $record) => array_map(
                        fn (array $item) => CarbonImmutable::parse($item['at'])->setTimezone($record->business->timezone)->format('j M, H:i').' · '.$item['text'],
                        BookingHistory::for($record),
                    ))
                    ->listWithLineBreaks()
                    ->bulleted(),
            ]),
        ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListBookings::route('/'),
            'view' => ViewBooking::route('/{record}'),
        ];
    }
}
