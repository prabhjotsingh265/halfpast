<?php

namespace App\Http\Controllers;

use App\Models\Business;
use App\Models\Service;
use Illuminate\Http\Response;

/**
 * The pages search engines are welcome to: the home page, the privacy
 * policy and terms, and the booking page of every business with something
 * to book. Plus robots.txt, which
 * keeps them out of everything else and points to the sitemap.
 */
class SitemapController extends Controller
{
    public function sitemap(): Response
    {
        $bookable = Service::withoutGlobalScopes()
            ->where('is_active', true)
            ->whereHas('staff', fn ($query) => $query->where('staff.is_active', true))
            ->select('business_id');

        $pages = [
            ['loc' => route('home'), 'lastmod' => null],
            ['loc' => route('privacy'), 'lastmod' => config('halfpast.legal_updated')],
            ['loc' => route('terms'), 'lastmod' => config('halfpast.legal_updated')],
        ];

        foreach (Business::whereIn('id', $bookable)->orderBy('id')->get(['slug', 'updated_at']) as $business) {
            $pages[] = ['loc' => route('booking.show', ['business' => $business->slug]), 'lastmod' => $business->updated_at?->toDateString()];
        }

        $xml = '<?xml version="1.0" encoding="UTF-8"?>'."\n".'<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'."\n";

        foreach ($pages as $page) {
            $xml .= '  <url><loc>'.e($page['loc']).'</loc>'.($page['lastmod'] ? '<lastmod>'.$page['lastmod'].'</lastmod>' : '')."</url>\n";
        }

        return response($xml."</urlset>\n", 200, ['Content-Type' => 'application/xml; charset=UTF-8']);
    }

    public function robots(): Response
    {
        $private = ['/dashboard', '/calendar', '/customers', '/services', '/staff', '/time-off', '/earnings', '/business', '/settings', '/admin', '/horizon', '/pulse', '/start', '/bookings'];

        // Exact paths and their sub-pages only: a plain prefix like /staff
        // would also hide a booking page such as /staffing-agency.
        $rules = collect($private)->flatMap(fn (string $path) => ["Disallow: {$path}$", "Disallow: {$path}/"])->join("\n");

        $text = "User-agent: *\n{$rules}\n\nSitemap: ".route('sitemap')."\n";

        return response($text, 200, ['Content-Type' => 'text/plain; charset=UTF-8']);
    }
}
