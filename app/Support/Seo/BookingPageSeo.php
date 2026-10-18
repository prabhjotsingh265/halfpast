<?php

namespace App\Support\Seo;

use App\Mail\Money;
use App\Models\Business;
use App\Models\Service;
use App\Models\Staff;
use App\Models\WorkingHour;
use Illuminate\Support\Collection;

/**
 * What search engines and link previews see for a booking page: a title, a
 * short description, the canonical address, and schema.org data saying
 * what the business offers, at what price and when it's open (the hours
 * anyone on the team works).
 */
final class BookingPageSeo
{
    private const DAYS = [1 => 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];

    /**
     * @param  Collection<int, Service>  $services  What can be booked online.
     * @return array{title: string, description: string, url: string, schema: array<string, mixed>}
     */
    public static function for(Business $business, Collection $services): array
    {
        $url = route('booking.show', ['business' => $business->slug]);
        $money = fn (int $minor) => Money::format($minor, $business->currency);
        $prices = $services->pluck('price');
        $names = $services->pluck('name')->take(3)->join(', ', ' and ');

        $description = $services->isEmpty()
            ? "Book online with {$business->name}."
            : "Book {$names} online with {$business->name}, from {$money((int) $prices->min())}. Pick a time and you're booked.";

        $schema = array_filter([
            '@context' => 'https://schema.org',
            '@type' => 'LocalBusiness',
            'name' => $business->name,
            'url' => $url,
            'currenciesAccepted' => $business->currency,
            'priceRange' => $prices->isEmpty() ? null : $money((int) $prices->min()).'–'.$money((int) $prices->max()),
            'openingHoursSpecification' => self::openingHours($business),
            'makesOffer' => $services->map(fn (Service $service) => [
                '@type' => 'Offer',
                'price' => number_format($service->price / 100, 2, '.', ''),
                'priceCurrency' => $business->currency,
                'itemOffered' => array_filter([
                    '@type' => 'Service',
                    'name' => $service->name,
                    'description' => $service->description,
                ]),
            ])->values()->all(),
            'potentialAction' => [
                '@type' => 'ReserveAction',
                'target' => $url,
            ],
        ], fn ($value) => $value !== null && $value !== []);

        return [
            'title' => "Book with {$business->name}",
            'description' => mb_strimwidth($description, 0, 160, '…'),
            'url' => $url,
            'schema' => $schema,
        ];
    }

    /**
     * Open whenever anyone taking bookings is working: each day's stretches
     * across the team, joined where they overlap.
     *
     * @return list<array<string, string>>
     */
    private static function openingHours(Business $business): array
    {
        $active = Staff::withoutGlobalScopes()->where('business_id', $business->id)->where('is_active', true)->pluck('id');
        $rows = WorkingHour::withoutGlobalScopes()->whereIn('staff_id', $active)->orderBy('starts_at')->get()->groupBy('weekday');
        $spec = [];

        foreach (self::DAYS as $weekday => $day) {
            $merged = [];

            foreach ($rows->get($weekday, collect()) as $hour) {
                [$from, $to] = [substr($hour->starts_at, 0, 5), substr($hour->ends_at, 0, 5)];
                $last = array_key_last($merged);

                if ($last !== null && $from <= $merged[$last][1]) {
                    $merged[$last][1] = max($merged[$last][1], $to);
                } else {
                    $merged[] = [$from, $to];
                }
            }

            foreach ($merged as [$opens, $closes]) {
                $spec[] = ['@type' => 'OpeningHoursSpecification', 'dayOfWeek' => "https://schema.org/{$day}", 'opens' => $opens, 'closes' => $closes === '24:00' ? '23:59' : $closes];
            }
        }

        return $spec;
    }
}
