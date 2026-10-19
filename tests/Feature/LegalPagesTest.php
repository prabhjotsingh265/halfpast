<?php

use Inertia\Testing\AssertableInertia as Assert;

it('publishes a privacy policy and terms that search engines may index', function (string $route, string $component, string $title) {
    $this->get(route($route))
        ->assertOk()
        ->assertSee("<title>{$title} - Halfpast</title>", false)
        ->assertDontSee('noindex', false)
        ->assertInertia(fn (Assert $page) => $page
            ->component($component)
            ->where('updated', config('halfpast.legal_updated'))
            ->where('contact.email', null)
            ->where('contact.issues', 'https://github.com/prabhjotsingh265/halfpast/issues'));
})->with([
    'privacy' => ['privacy', 'marketing/Privacy', 'Privacy policy'],
    'terms' => ['terms', 'marketing/Terms', 'Terms of use'],
]);

it('gives an email address for questions once there is one', function () {
    config(['halfpast.contact_email' => 'hello@halfpast.example']);

    $this->get(route('privacy'))->assertInertia(fn (Assert $page) => $page->where('contact.email', 'hello@halfpast.example'));
});

it('lists both in the sitemap, and keeps crawlers out of the admin tools', function () {
    $this->get('/sitemap.xml')
        ->assertSee('<loc>'.route('privacy').'</loc>', false)
        ->assertSee('<loc>'.route('terms').'</loc>', false);

    expect($this->get('/robots.txt')->getContent())
        ->toContain('Disallow: /horizon/')
        ->toContain('Disallow: /pulse$');
});
