<?php

use App\Enums\BusinessRole;
use App\Models\Business;
use App\Models\Service;
use App\Models\Staff;
use App\Models\User;
use App\Tenancy\CurrentBusiness;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    Storage::fake('public');
    $this->owner = User::factory()->create();
    $this->business = Business::factory()->ownedBy($this->owner)->create(['name' => 'Glow Studio', 'slug' => 'glow-studio']);
});

it('saves what the booking page says about the business', function () {
    $this->actingAs($this->owner)
        ->put(route('business.profile.update'), [
            'name' => ' Glow Studio & Spa ',
            'description' => 'Cuts, colour and calm.',
            'address' => '14 Park Street, Kolkata',
            'phone' => '+91 33 4000 1234',
            'website' => 'glowstudio.example',
        ])
        ->assertRedirect(route('business.profile.edit'))
        ->assertInertiaFlash('toast.message', 'Booking page details saved.');

    expect($this->business->fresh()->only(['name', 'description', 'address', 'phone', 'website']))->toBe([
        'name' => 'Glow Studio & Spa',
        'description' => 'Cuts, colour and calm.',
        'address' => '14 Park Street, Kolkata',
        'phone' => '+91 33 4000 1234',
        'website' => 'https://glowstudio.example',
    ]);
});

it('clears what is left blank, and turns down what is not a phone number or a web address', function () {
    $this->business->update(['address' => 'Somewhere']);

    $this->actingAs($this->owner)
        ->put(route('business.profile.update'), ['name' => 'Glow Studio', 'address' => '   ', 'phone' => 'call me', 'website' => 'not a site'])
        ->assertSessionHasErrors(['phone', 'website']);

    $this->actingAs($this->owner)
        ->put(route('business.profile.update'), ['name' => 'Glow Studio', 'address' => '   '])
        ->assertSessionHasNoErrors();

    expect($this->business->fresh()->address)->toBeNull();
});

it('keeps a logo in its own shape, small, as a PNG', function () {
    $this->actingAs($this->owner)
        ->post(route('business.logo.store'), ['logo' => UploadedFile::fake()->image('logo.jpg', 1200, 400)])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $path = $this->business->fresh()->logo_path;
    Storage::disk('public')->assertExists($path);
    [$width, $height, $type] = getimagesizefromstring(Storage::disk('public')->get($path));

    expect([$width, $height, $type])->toBe([256, 85, IMAGETYPE_PNG])
        ->and($path)->toStartWith("logos/{$this->business->id}/");
});

it('trims the empty margin round a logo', function (bool $seeThrough) {
    // A 400 by 200 picture with the logo, 100 by 50, in the middle.
    $image = imagecreatetruecolor(400, 200);
    imagealphablending($image, false);
    imagesavealpha($image, true);
    imagefill($image, 0, 0, $seeThrough ? imagecolorallocatealpha($image, 0, 0, 0, 127) : imagecolorallocate($image, 255, 255, 255));
    imagefilledrectangle($image, 150, 75, 249, 124, imagecolorallocate($image, 200, 80, 120));
    $file = tempnam(sys_get_temp_dir(), 'logo').'.png';
    imagepng($image, $file);

    $this->actingAs($this->owner)
        ->post(route('business.logo.store'), ['logo' => new UploadedFile($file, 'logo.png', 'image/png', null, true)])
        ->assertSessionHasNoErrors();

    [$width, $height] = getimagesizefromstring(Storage::disk('public')->get($this->business->fresh()->logo_path));
    expect([$width, $height])->toBe([100, 50]);
})->with(['see-through' => true, 'on white' => false]);

it('replaces the old logo, and removes it on request', function () {
    $this->actingAs($this->owner)->post(route('business.logo.store'), ['logo' => UploadedFile::fake()->image('a.png', 300, 300)]);
    $first = $this->business->fresh()->logo_path;

    $this->actingAs($this->owner)->post(route('business.logo.store'), ['logo' => UploadedFile::fake()->image('b.png', 300, 300)]);
    $second = $this->business->fresh()->logo_path;

    Storage::disk('public')->assertMissing($first);
    Storage::disk('public')->assertExists($second);

    $this->actingAs($this->owner)->delete(route('business.logo.destroy'))->assertRedirect();

    Storage::disk('public')->assertMissing($second);
    expect($this->business->fresh()->logo_path)->toBeNull();
});

it("won't take a drawing or a file that isn't a picture", function () {
    $svg = UploadedFile::fake()->createWithContent('logo.svg', '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>');

    $this->actingAs($this->owner)
        ->post(route('business.logo.store'), ['logo' => $svg])
        ->assertSessionHasErrors('logo');

    expect($this->business->fresh()->logo_path)->toBeNull();
});

it('is for owners only', function () {
    $staff = User::factory()->create();
    $this->business->addMember($staff, BusinessRole::Staff);

    $this->actingAs($staff)->get(route('business.profile.edit'))->assertForbidden();
    $this->actingAs($staff)->post(route('business.logo.store'), ['logo' => UploadedFile::fake()->image('a.png', 300, 300)])->assertForbidden();
});

it('shows the profile on the booking page and to search engines', function () {
    $this->business->update(['description' => 'Cuts, colour and calm.', 'address' => '14 Park Street, Kolkata', 'phone' => '+91 33 4000 1234']);
    $this->business->forceFill(['logo_path' => 'logos/1/logo.png'])->save();
    app(CurrentBusiness::class)->set($this->business);
    $service = Service::factory()->for($this->business)->create();
    $service->staff()->attach(Staff::factory()->for($this->business)->create());
    app(CurrentBusiness::class)->set(null);

    $response = $this->get('/glow-studio')->assertOk()
        ->assertSee('<meta name="description" content="Cuts, colour and calm.">', false)
        ->assertInertia(fn (Assert $page) => $page
            ->where('business.description', 'Cuts, colour and calm.')
            ->where('business.address', '14 Park Street, Kolkata')
            ->where('business.logo_url', Storage::disk('public')->url('logos/1/logo.png')));

    preg_match('#<script type="application/ld\+json"[^>]*>(.*?)</script>#s', $response->getContent(), $match);
    $schema = json_decode($match[1], true);

    expect($schema['telephone'])->toBe('+91 33 4000 1234')
        ->and($schema['address'])->toBe('14 Park Street, Kolkata')
        ->and($schema['logo'])->toBe(Storage::disk('public')->url('logos/1/logo.png'));
});

it('takes the logo with it when the business is deleted', function () {
    $this->owner->forceFill(['password' => bcrypt('secret-password')])->save();
    $this->actingAs($this->owner)->post(route('business.logo.store'), ['logo' => UploadedFile::fake()->image('a.png', 300, 300)]);
    $path = $this->business->fresh()->logo_path;

    $this->actingAs($this->owner)->delete(route('business.destroy'), ['name' => 'Glow Studio', 'password' => 'secret-password']);

    Storage::disk('public')->assertMissing($path);
});
