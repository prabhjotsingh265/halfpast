<?php

namespace App\Actions\Businesses;

use App\Models\Booking;
use App\Models\Business;
use App\Models\Customer;
use App\Models\Payment;
use App\Models\Refund;
use App\Models\Service;
use App\Models\Staff;
use App\Models\TimeOff;
use App\Models\WorkingHour;
use Carbon\CarbonImmutable;
use DateTimeInterface;
use RuntimeException;
use ZipArchive;

/**
 * Everything a business has in Halfpast, as a ZIP of CSV files a
 * spreadsheet can open: bookings, customers, payments, refunds, services,
 * the team and time off, plus its settings. Times are given both in the
 * business's own time and in UTC; money in whole units, like 12.50.
 */
class ExportBusinessData
{
    /** @return string Path to the ZIP file, which the caller deletes after sending. */
    public function handle(Business $business): string
    {
        $path = tempnam(sys_get_temp_dir(), 'halfpast-export-').'.zip';
        $zip = new ZipArchive;

        if ($zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('The export file could not be created.');
        }

        $zone = $business->timezone;
        $local = fn (?DateTimeInterface $moment) => $moment === null ? '' : CarbonImmutable::instance($moment)->setTimezone($zone)->format('Y-m-d H:i');
        $utc = fn (?DateTimeInterface $moment) => $moment === null ? '' : CarbonImmutable::instance($moment)->utc()->toIso8601ZuluString();
        $money = fn (int $minor) => number_format($minor / 100, 2, '.', '');
        $staffNames = Staff::withoutGlobalScopes()->where('business_id', $business->id)->pluck('name', 'id');

        $bookings = Booking::withoutGlobalScopes()->where('business_id', $business->id)->with('customer')->orderBy('starts_at')->get();
        $zip->addFromString('bookings.csv', self::csv(
            ['reference', 'service', 'with', 'customer', 'customer_email', 'starts_local', 'ends_local', 'starts_utc', 'status', 'source', 'price', 'deposit', 'currency', 'notes', 'booked_at_utc', 'cancelled_at_utc', 'cancelled_by'],
            $bookings->map(fn (Booking $b) => [
                $b->displayCode(), $b->service_name, $staffNames[$b->staff_id] ?? '', $b->customer->name ?? '', $b->customer->email ?? '',
                $local($b->starts_at), $local($b->ends_at), $utc($b->starts_at), $b->status->value, $b->source,
                $money($b->price), $money($b->deposit), $b->currency, $b->notes ?? '', $utc($b->created_at), $utc($b->cancelled_at), $b->cancelled_by ?? '',
            ]),
        ));

        $zip->addFromString('customers.csv', self::csv(
            ['name', 'email', 'phone', 'notes', 'added_utc'],
            Customer::withoutGlobalScopes()->where('business_id', $business->id)->whereNull('forgotten_at')->orderBy('name')->get()
                ->map(fn (Customer $c) => [$c->name, $c->email ?? '', $c->phone ?? '', $c->notes ?? '', $utc($c->created_at)]),
        ));

        $payments = Payment::withoutGlobalScopes()->where('business_id', $business->id)->with('booking')->orderBy('id')->get();
        $zip->addFromString('payments.csv', self::csv(
            ['id', 'booking', 'gateway', 'amount', 'refunded', 'currency', 'status', 'outcome', 'gateway_payment', 'captured_utc', 'started_utc'],
            $payments->map(fn (Payment $p) => [
                $p->id, $p->booking?->displayCode() ?? '', $p->gateway, $money($p->amount), $money($p->refunded_amount), $p->currency,
                $p->status->value, $p->outcome->value ?? '', $p->gateway_payment_id ?? '', $utc($p->captured_at), $utc($p->created_at),
            ]),
        ));

        $zip->addFromString('refunds.csv', self::csv(
            ['payment', 'amount', 'status', 'reason', 'gateway_refund', 'requested_utc'],
            Refund::withoutGlobalScopes()->where('business_id', $business->id)->orderBy('id')->get()
                ->map(fn (Refund $r) => [$r->payment_id, $money($r->amount), $r->status->value, $r->reason, $r->gateway_refund_id ?? '', $utc($r->created_at)]),
        ));

        $zip->addFromString('services.csv', self::csv(
            ['name', 'description', 'minutes', 'tidy_up_minutes', 'price', 'deposit_type', 'deposit_value', 'on_sale'],
            Service::withoutGlobalScopes()->where('business_id', $business->id)->orderBy('position')->get()
                ->map(fn (Service $s) => [$s->name, $s->description ?? '', $s->duration_minutes, $s->buffer_after_minutes, $money($s->price), $s->deposit_type->value, $s->deposit_value, $s->is_active ? 'yes' : 'no']),
        ));

        $hours = WorkingHour::withoutGlobalScopes()->where('business_id', $business->id)->orderBy('weekday')->orderBy('starts_at')->get()->groupBy('staff_id');
        $days = [1 => 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];
        $zip->addFromString('team.csv', self::csv(
            ['name', 'bio', 'taking_bookings', 'hours'],
            Staff::withoutGlobalScopes()->where('business_id', $business->id)->orderBy('position')->get()
                ->map(fn (Staff $s) => [
                    $s->name, $s->bio ?? '', $s->is_active ? 'yes' : 'no',
                    $hours->get($s->id, collect())->map(fn (WorkingHour $h) => $days[$h->weekday].' '.substr($h->starts_at, 0, 5).'-'.substr($h->ends_at, 0, 5))->join('; '),
                ]),
        ));

        $zip->addFromString('time-off.csv', self::csv(
            ['who', 'from_local', 'until_local', 'reason'],
            TimeOff::withoutGlobalScopes()->where('business_id', $business->id)->orderBy('starts_at')->get()
                ->map(fn (TimeOff $t) => [$t->staff_id === null ? 'Everyone (closed)' : ($staffNames[$t->staff_id] ?? ''), $local($t->starts_at), $local($t->ends_at), $t->reason ?? '']),
        ));

        $zip->addFromString('business.json', (string) json_encode([
            'name' => $business->name,
            'description' => $business->description,
            'address' => $business->address,
            'phone' => $business->phone,
            'website' => $business->website,
            'logo' => $business->logoUrl(),
            'booking_page' => route('booking.show', ['business' => $business->slug]),
            'timezone' => $zone,
            'currency' => $business->currency,
            'slot_step_minutes' => $business->slot_step_minutes,
            'min_notice_minutes' => $business->min_notice_minutes,
            'booking_horizon_days' => $business->booking_horizon_days,
            'cancellation_window_hours' => $business->cancellation_window_hours,
            'hold_minutes' => $business->hold_minutes,
            'payment_methods' => $business->payment_gateways,
            'exported_utc' => now()->utc()->toIso8601ZuluString(),
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

        $zip->addFromString('README.txt', "Everything {$business->name} had in Halfpast on ".now($zone)->format('j F Y').".\n\n"
            ."Times ending in _local are in {$zone}; times ending in _utc are in UTC.\n"
            ."Money is in {$business->currency}, in whole units (12.50, not 1250).\n");

        $zip->close();

        return $path;
    }

    /**
     * @param  list<string>  $header
     * @param  iterable<array<int, mixed>>  $rows
     */
    private static function csv(array $header, iterable $rows): string
    {
        $out = fopen('php://temp', 'r+');

        if ($out === false) {
            throw new RuntimeException('The export could not be written.');
        }

        fputcsv($out, $header, escape: '');

        foreach ($rows as $row) {
            fputcsv($out, array_map(self::safe(...), $row), escape: '');
        }

        rewind($out);
        $csv = (string) stream_get_contents($out);
        fclose($out);

        return $csv;
    }

    /**
     * A spreadsheet runs anything starting with =, +, - or @ as a formula,
     * and customers type their own names and notes. Those get a leading
     * apostrophe so they show as plain text.
     */
    private static function safe(mixed $value): string
    {
        $value = (string) $value;

        return $value !== '' && in_array($value[0], ['=', '+', '-', '@', "\t", "\r"], true) && ! is_numeric($value) ? "'".$value : $value;
    }
}
