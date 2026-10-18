<?php

namespace App\Models;

use App\Enums\BookingStatus;
use App\Observers\BookingObserver;
use App\Support\Calendar\IcsEvent;
use App\Tenancy\BelongsToBusiness;
use Carbon\CarbonImmutable;
use Database\Factories\BookingFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

/**
 * An appointment, from the moment a customer holds a time onwards.
 *
 * @property int $id
 * @property int $business_id
 * @property int|null $service_id
 * @property int|null $staff_id
 * @property int|null $customer_id
 * @property string $service_name
 * @property CarbonImmutable $starts_at
 * @property CarbonImmutable $ends_at
 * @property CarbonImmutable $blocked_until
 * @property BookingStatus $status
 * @property CarbonImmutable|null $hold_expires_at
 * @property string $source
 * @property int $price
 * @property int $deposit
 * @property string $currency
 * @property string $code
 * @property string|null $notes
 * @property CarbonImmutable|null $cancelled_at
 * @property string|null $cancelled_by
 * @property int $refund_due
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
#[ObservedBy(BookingObserver::class)]
#[Fillable([
    'service_id',
    'staff_id',
    'customer_id',
    'service_name',
    'starts_at',
    'ends_at',
    'blocked_until',
    'status',
    'hold_expires_at',
    'source',
    'price',
    'deposit',
    'currency',
    'code',
    'notes',
    'cancelled_at',
    'cancelled_by',
    'refund_due',
])]
class Booking extends Model
{
    use BelongsToBusiness;

    /** @use HasFactory<BookingFactory> */
    use HasFactory;

    /** PostgreSQL's code for a row refused by an exclusion constraint. */
    public const OVERLAP_ERROR = '23P01';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => BookingStatus::class,
            'starts_at' => 'immutable_datetime',
            'ends_at' => 'immutable_datetime',
            'blocked_until' => 'immutable_datetime',
            'hold_expires_at' => 'immutable_datetime',
            'cancelled_at' => 'immutable_datetime',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }

    /**
     * Ten characters from an alphabet without look-alikes (no 0/O, 1/I/L),
     * so the code can be read out over the phone.
     */
    public static function newCode(): string
    {
        $alphabet = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';

        return implode('', array_map(
            fn () => $alphabet[random_int(0, strlen($alphabet) - 1)],
            range(1, 10),
        ));
    }

    protected static function booted(): void
    {
        static::creating(function (Booking $booking): void {
            $booking->code ??= self::newCode();
        });
    }

    /**
     * Bookings that take up time right now: confirmed ones, and holds that
     * haven't run out yet.
     *
     * @param  Builder<Booking>  $query
     */
    public function scopeTakingTime(Builder $query, ?CarbonImmutable $at = null): void
    {
        $at ??= CarbonImmutable::now();

        $query->where(function (Builder $query) use ($at) {
            $query->where('status', BookingStatus::Confirmed)
                ->orWhere(fn (Builder $query) => $query
                    ->where('status', BookingStatus::Held)
                    ->where('hold_expires_at', '>', $at));
        });
    }

    /**
     * Bookings a calendar shows: confirmed, finished or missed ones, and
     * holds that haven't run out.
     *
     * @param  Builder<Booking>  $query
     */
    public function scopeShownOnCalendar(Builder $query): void
    {
        $query->where(fn (Builder $query) => $query
            ->whereIn('status', [BookingStatus::Confirmed, BookingStatus::Completed, BookingStatus::NoShow])
            ->orWhere(fn (Builder $query) => $query
                ->where('status', BookingStatus::Held)
                ->where('hold_expires_at', '>', CarbonImmutable::now())));
    }

    public function isHeld(): bool
    {
        return $this->status === BookingStatus::Held
            && $this->hold_expires_at !== null
            && $this->hold_expires_at->isFuture();
    }

    /**
     * @return BelongsTo<Business, $this>
     */
    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    /**
     * The reminders sent for it, one per kind.
     *
     * @return HasMany<BookingReminder, $this>
     */
    public function reminders(): HasMany
    {
        return $this->hasMany(BookingReminder::class);
    }

    /**
     * @return HasMany<Payment, $this>
     */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    /**
     * @return BelongsTo<Service, $this>
     */
    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    /**
     * @return BelongsTo<Staff, $this>
     */
    public function staff(): BelongsTo
    {
        return $this->belongsTo(Staff::class);
    }

    /**
     * @return BelongsTo<Customer, $this>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function getRouteKeyName(): string
    {
        return 'code';
    }

    /**
     * The customer's own link to their booking, signed so it can't be
     * guessed or altered. It's in their confirmation email.
     */
    public function manageUrl(): string
    {
        return URL::signedRoute('booking.manage', [
            'business' => $this->business()->value('slug'),
            'booking' => $this->code,
        ]);
    }

    /** The same booking as a calendar file, signed like the manage link. */
    public function calendarUrl(): string
    {
        return URL::signedRoute('booking.calendar', [
            'business' => $this->business()->value('slug'),
            'booking' => $this->code,
        ]);
    }

    public function calendarEvent(): IcsEvent
    {
        $this->loadMissing(['business', 'staff']);
        $manage = $this->manageUrl();

        return new IcsEvent(
            uid: $this->code.'@halfpast',
            start: $this->starts_at,
            end: $this->ends_at,
            summary: "{$this->service_name} at {$this->business->name}",
            description: implode("\n", array_filter([
                $this->staff ? "With {$this->staff->name}." : null,
                "Booking reference {$this->displayCode()}.",
                "View or cancel: {$manage}",
            ])),
            location: $this->business->name,
            url: $manage,
        );
    }

    /** Readable in a list: "7K2Q-9XMB-AT". */
    public function displayCode(): string
    {
        return implode('-', str_split(Str::upper($this->code), 4));
    }
}
