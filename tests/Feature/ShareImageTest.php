<?php

use App\Actions\Staff\SaveWeeklyHours;
use App\Models\Business;
use App\Models\Service;
use App\Models\Staff;
use App\Support\ShareImage;
use App\Tenancy\CurrentBusiness;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('public');
    $this->business = Business::factory()->create(['name' => 'Glow Studio', 'slug' => 'glow-studio', 'description' => 'Cuts, colour and calm.']);
    app(CurrentBusiness::class)->set($this->business);
    $this->priya = Staff::factory()->for($this->business)->create();
    Service::factory()->for($this->business)->create()->staff()->attach($this->priya);
    app(SaveWeeklyHours::class)->handle($this->priya, [['weekday' => 2, 'starts_at' => '10:00', 'ends_at' => '19:30']]);
    app(CurrentBusiness::class)->set(null);
});

it('gives each booking page its own preview picture', function () {
    $html = $this->get('/glow-studio')->getContent();
    $image = route('booking.share', ['business' => 'glow-studio', 'v' => ShareImage::key($this->business)]);

    expect($html)->toContain('<meta property="og:image" content="'.e($image).'">')
        ->toContain('<meta name="twitter:card" content="summary_large_image">');

    $response = $this->get($image)->assertOk()->assertHeader('Content-Type', 'image/png');
    expect($response->headers->get('Cache-Control'))->toContain('max-age=86400');

    [$width, $height] = getimagesize($response->baseResponse->getFile()->getPathname());
    expect([$width, $height])->toBe([1200, 630]);
});

it('runs the colours from the opening hour to the closing hour', function () {
    expect(ShareImage::workingDay($this->business))->toBe([10.0, 19.5]);
});

it('draws a new picture when the business changes, and keeps only that one', function () {
    $this->get(route('booking.share', ['business' => 'glow-studio']));
    $first = ShareImage::key($this->business);

    $this->business->update(['description' => 'Now with a nail bar.']);
    $second = ShareImage::key($this->business);
    $this->get(route('booking.share', ['business' => 'glow-studio']))->assertOk();

    expect($second)->not->toBe($first)
        ->and(Storage::disk('public')->files("share/{$this->business->id}"))->toBe(["share/{$this->business->id}/{$second}.png"]);
});

it('puts the logo on it when there is one', function () {
    Storage::disk('public')->put('logos/glow.png', UploadedFile::fake()->image('glow.png', 200, 80)->getContent());
    $this->business->forceFill(['logo_path' => 'logos/glow.png'])->save();

    $this->get(route('booking.share', ['business' => 'glow-studio']))->assertOk();

    expect(ShareImage::key($this->business))->not->toBe(ShareImage::key($this->business->replicate()->forceFill(['logo_path' => null])));
});

it('has one for Halfpast itself, on the home page and the small print', function () {
    $this->get('/')->assertSee('<meta property="og:image" content="'.route('share.halfpast').'">', false);
    $this->get('/privacy')->assertSee(route('share.halfpast'), false);

    $this->get('/share.png')->assertOk()->assertHeader('Content-Type', 'image/png');
});
