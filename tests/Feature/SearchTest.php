<?php

use App\Models\Business;
use App\Models\Service;
use App\Models\Staff;
use App\Models\User;
use App\Models\WorkingHour;
use App\Tenancy\CurrentBusiness;

beforeEach(function () {
    $this->business = Business::factory()->create(['name' => 'Glow Studio', 'slug' => 'glow-studio', 'currency' => 'GBP']);
    app(CurrentBusiness::class)->set($this->business);

    $priya = Staff::factory()->for($this->business)->create();
    $sam = Staff::factory()->for($this->business)->create();
    $colour = Service::factory()->for($this->business)->create(['name' => 'Colour', 'price' => 6000, 'description' => 'Global colour.']);
    $trim = Service::factory()->for($this->business)->create(['name' => 'Trim', 'price' => 2500]);
    $colour->staff()->attach([$priya->id, $sam->id]);
    $trim->staff()->attach($priya);

    // Monday: 09:00-13:00 and 12:00-17:00 overlap into one stretch; Saturday is short.
    foreach ([[$priya, 1, '09:00', '13:00'], [$sam, 1, '12:00', '17:00'], [$sam, 6, '10:00', '14:00']] as [$who, $day, $from, $to]) {
        $hour = new WorkingHour(['staff_id' => $who->id, 'weekday' => $day, 'starts_at' => $from, 'ends_at' => $to]);
        $hour->business_id = $this->business->id;
        $hour->save();
    }

    app(CurrentBusiness::class)->set(null);
});

/** The schema.org data in a page. */
function schemaOf(string $html): array
{
    preg_match('#<script type="application/ld\+json"[^>]*>(.*?)</script>#s', $html, $match);

    return json_decode($match[1] ?? '{}', true);
}

it('gives a booking page its own title, description and share tags, in the page itself', function () {
    $html = $this->get('/glow-studio')->assertOk()->getContent();

    expect($html)->toContain('<title>Book with Glow Studio - Halfpast</title>')
        ->toContain('<meta name="description" content="Book Colour and Trim online with Glow Studio, from £25. Pick a time and you&#039;re booked.">')
        ->toContain('<link rel="canonical" href="'.route('booking.show', ['business' => 'glow-studio']).'">')
        ->toContain('<meta property="og:title" content="Book with Glow Studio">')
        ->not->toContain('noindex');
});

it('tells search engines what the business offers and when it is open', function () {
    $schema = schemaOf($this->get('/glow-studio')->getContent());

    expect($schema['@type'])->toBe('LocalBusiness')
        ->and($schema['name'])->toBe('Glow Studio')
        ->and($schema['priceRange'])->toBe('£25–£60')
        ->and(array_column(array_column($schema['makesOffer'], 'itemOffered'), 'name'))->toBe(['Colour', 'Trim'])
        ->and($schema['makesOffer'][0]['price'])->toBe('60.00')
        ->and($schema['openingHoursSpecification'])->toBe([
            ['@type' => 'OpeningHoursSpecification', 'dayOfWeek' => 'https://schema.org/Monday', 'opens' => '09:00', 'closes' => '17:00'],
            ['@type' => 'OpeningHoursSpecification', 'dayOfWeek' => 'https://schema.org/Saturday', 'opens' => '10:00', 'closes' => '14:00'],
        ]);
});

it("can't be broken out of by a business name", function () {
    $this->business->update(['name' => 'Evil </script><script>alert(1)</script>']);

    $html = $this->get('/glow-studio')->getContent();

    expect($html)->not->toContain('</script><script>alert(1)')
        ->and(schemaOf($html)['name'])->toBe('Evil </script><script>alert(1)</script>');
});

it('keeps the dashboard, checkout and sign-in pages out of search results', function () {
    $this->get('/login')->assertSee('<meta name="robots" content="noindex, nofollow">', false);

    $owner = User::factory()->create();
    Business::factory()->ownedBy($owner)->create();
    $this->actingAs($owner)->get(route('dashboard'))->assertSee('noindex, nofollow', false);
});

it('describes the home page and lets it be found', function () {
    $this->get('/')
        ->assertSee('<meta name="description" content="Halfpast gives salons, clinics and tutors a booking page', false)
        ->assertDontSee('noindex', false);
});

it('lists the home page and every bookable business in the sitemap', function () {
    $empty = Business::factory()->create(['slug' => 'nothing-yet']);

    $this->get('/sitemap.xml')
        ->assertOk()
        ->assertHeader('Content-Type', 'application/xml; charset=UTF-8')
        ->assertSee('<loc>'.route('home').'</loc>', false)
        ->assertSee('<loc>'.route('booking.show', ['business' => 'glow-studio']).'</loc>', false)
        ->assertDontSee('nothing-yet');

    expect($empty->exists)->toBeTrue();
});

it('keeps crawlers out of the app without hiding booking pages that start the same way', function () {
    $robots = $this->get('/robots.txt')->assertOk()->getContent();

    expect($robots)->toContain('Disallow: /dashboard$')
        ->toContain('Disallow: /staff/')
        ->not->toMatch('#^Disallow: /staff$#m')
        ->toContain('Sitemap: '.route('sitemap'));
});
