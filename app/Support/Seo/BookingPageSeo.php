<?php

namespace App\Support\Seo;

use App\Mail\Money;
use App\Models\Business;
use App\Models\Service;
use App\Support\OpeningHours;
use App\Support\ShareImage;
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
     * @return array{title: string, description: string, url: string, image: string, schema: array<string, mixed>}
     */
    public static function for(Business $business, Collection $services): array
    {
        $url = route('booking.show', ['business' => $business->slug]);
        $money = fn (int $minor) => Money::format($minor, $business->currency);
        $prices = $services->pluck('price');
        $names = $services->pluck('name')->take(3)->join(', ', ' and ');

        // The business's own words if it has some; otherwise, what it offers.
        $description = $business->description ?? ($services->isEmpty()
            ? "Book online with {$business->name}."
            : "Book {$names} online with {$business->name}, from {$money((int) $prices->min())}. Pick a time and you're booked.");

        $schema = array_filter([
            '@context' => 'https://schema.org',
            '@type' => 'LocalBusiness',
            'name' => $business->name,
            'description' => $business->description,
            'url' => $url,
            'logo' => $business->logoUrl(),
            'image' => $business->logoUrl(),
            'telephone' => $business->phone,
            'address' => $business->address,
            'sameAs' => $business->website,
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
            // The fingerprint changes with the picture, so previews fetch the new one.
            'image' => route('booking.share', ['business' => $business->slug, 'v' => ShareImage::key($business)]),
            'schema' => $schema,
        ];
    }

    /**
     * @return list<array<string, string>>
     */
    private static function openingHours(Business $business): array
    {
        $spec = [];

        foreach (OpeningHours::week($business) as $weekday => $stretches) {
            foreach ($stretches as [$opens, $closes]) {
                $spec[] = ['@type' => 'OpeningHoursSpecification', 'dayOfWeek' => 'https://schema.org/'.self::DAYS[$weekday], 'opens' => $opens, 'closes' => $closes === '24:00' ? '23:59' : $closes];
            }
        }

        return $spec;
    }
}
