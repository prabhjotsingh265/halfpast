<?php

namespace App\Filament\Resources\Payments;

use App\Enums\PaymentStatus;
use App\Filament\Resources\Payments\Pages\ListPayments;
use App\Filament\Resources\Payments\Pages\ViewPayment;
use App\Mail\Money;
use App\Models\Payment;
use App\Models\Refund;
use BackedEnum;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

/**
 * Every deposit attempt on the platform, with what became of it.
 */
class PaymentResource extends Resource
{
    protected static ?string $model = Payment::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBanknotes;

    protected static string|UnitEnum|null $navigationGroup = 'Money';

    protected static ?int $navigationSort = 1;

    public static function canCreate(): bool
    {
        return false;
    }

    public static function statusColour(PaymentStatus $status): string
    {
        return match ($status) {
            PaymentStatus::Captured => 'success',
            PaymentStatus::Authorized, PaymentStatus::Pending => 'warning',
            PaymentStatus::Failed => 'danger',
            default => 'gray',
        };
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['business', 'booking']))
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('id')->label('#')->sortable(),
                TextColumn::make('business.name')->searchable(),
                TextColumn::make('booking.code')->label('Booking')->formatStateUsing(fn (Payment $record) => $record->booking?->displayCode())->searchable(),
                TextColumn::make('gateway')->badge()->color('gray'),
                TextColumn::make('amount')->money(fn (Payment $record) => $record->currency, divideBy: 100)->sortable(),
                TextColumn::make('refunded_amount')->label('Refunded')->money(fn (Payment $record) => $record->currency, divideBy: 100)->toggleable(),
                TextColumn::make('status')->badge()->color(fn (PaymentStatus $state) => self::statusColour($state)),
                TextColumn::make('outcome')->badge()->color('gray')->placeholder('Not settled'),
                TextColumn::make('created_at')->label('Started')->since()->sortable(),
            ])
            ->filters([
                SelectFilter::make('gateway')->options(['stripe' => 'Stripe', 'razorpay' => 'Razorpay', 'paypal' => 'PayPal', 'test' => 'Test']),
                SelectFilter::make('status')->options(collect(PaymentStatus::cases())->mapWithKeys(fn (PaymentStatus $s) => [$s->value => ucfirst(str_replace('_', ' ', $s->value))])->all()),
                SelectFilter::make('business')->relationship('business', 'name')->searchable()->preload(),
            ])
            ->recordActions([ViewAction::make()]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('The payment')->columns(3)->schema([
                TextEntry::make('id')->label('#'),
                TextEntry::make('business.name'),
                TextEntry::make('booking.code')->label('Booking')->formatStateUsing(fn (Payment $record) => $record->booking?->displayCode()),
                TextEntry::make('gateway')->badge(),
                TextEntry::make('amount')->money(fn (Payment $record) => $record->currency, divideBy: 100),
                TextEntry::make('refunded_amount')->label('Refunded')->money(fn (Payment $record) => $record->currency, divideBy: 100),
                TextEntry::make('status')->badge()->color(fn (PaymentStatus $state) => self::statusColour($state)),
                TextEntry::make('outcome')->badge()->placeholder('Not settled'),
                TextEntry::make('release_reason')->label('Why it was let go')->placeholder('—'),
            ]),
            Section::make('At the gateway')->columns(2)->schema([
                TextEntry::make('gateway_order_id')->label('Order or session')->copyable()->placeholder('—'),
                TextEntry::make('gateway_payment_id')->label('Payment')->copyable()->placeholder('—'),
                TextEntry::make('authorized_at')->dateTime('j M Y, H:i:s')->placeholder('—'),
                TextEntry::make('captured_at')->dateTime('j M Y, H:i:s')->placeholder('—'),
                TextEntry::make('released_at')->dateTime('j M Y, H:i:s')->placeholder('—'),
                TextEntry::make('checked_at')->label('Last checked')->dateTime('j M Y, H:i:s')->placeholder('—'),
                TextEntry::make('failure_reason')->columnSpanFull()->placeholder('—'),
            ]),
            Section::make('Refunds')->schema([
                TextEntry::make('refund_list')->hiddenLabel()
                    ->state(fn (Payment $record) => Refund::withoutGlobalScopes()->where('payment_id', $record->id)->orderBy('id')->get()
                        ->map(fn (Refund $refund) => Money::format($refund->amount, $record->currency)." · {$refund->status->value} · {$refund->reason}".($refund->last_error ? " · {$refund->last_error}" : ''))
                        ->all())
                    ->listWithLineBreaks()
                    ->bulleted()
                    ->placeholder('None'),
            ]),
        ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPayments::route('/'),
            'view' => ViewPayment::route('/{record}'),
        ];
    }
}
