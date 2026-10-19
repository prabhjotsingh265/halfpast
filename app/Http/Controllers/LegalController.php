<?php

namespace App\Http\Controllers;

use Inertia\Inertia;
use Inertia\Response;

/** The privacy policy and terms: plain words about what the app really does. */
class LegalController extends Controller
{
    public function privacy(): Response
    {
        return $this->page('marketing/Privacy', 'privacy', 'Privacy policy', 'What Halfpast keeps about businesses and the people who book with them, why, for how long, and who else sees it.');
    }

    public function terms(): Response
    {
        return $this->page('marketing/Terms', 'terms', 'Terms of use', 'The rules for using Halfpast: your account, your booking page, payments in test mode, and what we can and can\'t promise.');
    }

    private function page(string $component, string $route, string $title, string $description): Response
    {
        return Inertia::render($component, [
            'updated' => config('halfpast.legal_updated'),
            'contact' => [
                'email' => config('halfpast.contact_email'),
                'issues' => rtrim((string) config('halfpast.source_url'), '/').'/issues',
            ],
            'seo' => [
                'title' => $title,
                'description' => $description,
                'url' => route($route),
                'image' => route('share.halfpast'),
                'schema' => ['@context' => 'https://schema.org', '@type' => 'WebPage', 'name' => $title, 'url' => route($route)],
            ],
        ]);
    }
}
