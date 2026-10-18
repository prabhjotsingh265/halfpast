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
    $this->business = Business::factory()->ownedBy($this->owner)->create(['slug' => 'glow-studio']);
    app(CurrentBusiness::class)->set($this->business);
    $this->priya = Staff::factory()->for($this->business)->create(['name' => 'Priya']);
});

function storedImage(string $path): array
{
    $info = getimagesizefromstring(Storage::disk('public')->get($path));

    return [$info[0], $info[1], $info['mime']];
}

it('saves a photo as a small square WebP', function () {
    $this->actingAs($this->owner)
        ->from(route('staff.edit', $this->priya))
        ->post(route('staff.photo.store', $this->priya), ['photo' => UploadedFile::fake()->image('priya.jpg', 1200, 800)])
        ->assertRedirect(route('staff.edit', $this->priya))
        ->assertInertiaFlash('toast.message', 'Photo saved.');

    $path = $this->priya->fresh()->photo_path;

    expect($path)->toStartWith("staff/{$this->business->id}/")->toEndWith('.webp')
        ->and(storedImage($path))->toBe([320, 320, 'image/webp'])
        ->and($this->priya->fresh()->photoUrl())->toEndWith($path);
});

it('keeps a portrait photo square too, and deletes the old one when replaced', function () {
    $this->actingAs($this->owner)->post(route('staff.photo.store', $this->priya), ['photo' => UploadedFile::fake()->image('first.png', 400, 900)]);
    $first = $this->priya->fresh()->photo_path;

    $this->post(route('staff.photo.store', $this->priya), ['photo' => UploadedFile::fake()->image('second.webp', 500, 500)]);
    $second = $this->priya->fresh()->photo_path;

    expect($second)->not->toBe($first)
        ->and(storedImage($second))->toBe([320, 320, 'image/webp']);
    Storage::disk('public')->assertMissing($first);
    Storage::disk('public')->assertExists($second);
});

it('removes a photo', function () {
    $this->actingAs($this->owner)->post(route('staff.photo.store', $this->priya), ['photo' => UploadedFile::fake()->image('priya.jpg', 600, 600)]);
    $path = $this->priya->fresh()->photo_path;

    $this->delete(route('staff.photo.destroy', $this->priya))->assertInertiaFlash('toast.message', 'Photo removed.');

    expect($this->priya->fresh()->photo_path)->toBeNull();
    Storage::disk('public')->assertMissing($path);
});

it('takes only reasonable pictures', function (Closure $file, string $message) {
    $this->actingAs($this->owner)
        ->post(route('staff.photo.store', $this->priya), ['photo' => $file()])
        ->assertSessionHasErrors(['photo' => $message]);

    expect($this->priya->fresh()->photo_path)->toBeNull();
})->with([
    'not a picture' => [fn () => UploadedFile::fake()->create('cv.pdf', 100, 'application/pdf'), 'Choose a picture: a JPG, PNG or WebP.'],
    'too small' => [fn () => UploadedFile::fake()->image('tiny.jpg', 60, 60), 'Use a picture at least 100 pixels across and no more than 6000.'],
    'too large a file' => [fn () => UploadedFile::fake()->image('huge.jpg', 800, 800)->size(11000), 'That picture is over 10 MB. Try a smaller one.'],
]);

it('takes a photo with someone new', function () {
    $service = Service::factory()->for($this->business)->create();

    $this->actingAs($this->owner)->post(route('staff.store'), [
        'name' => 'Sam',
        'is_active' => true,
        'service_ids' => [$service->id],
        'hours' => [['weekday' => 1, 'starts_at' => '09:00', 'ends_at' => '17:00']],
        'photo' => UploadedFile::fake()->image('sam.jpg', 700, 700),
    ])->assertRedirect(route('staff.index'));

    $sam = Staff::where('name', 'Sam')->sole();
    expect($sam->photo_path)->not->toBeNull()
        ->and(storedImage($sam->photo_path))->toBe([320, 320, 'image/webp']);
});

it('deletes the photo along with the person', function () {
    $this->actingAs($this->owner)->post(route('staff.photo.store', $this->priya), ['photo' => UploadedFile::fake()->image('priya.jpg', 600, 600)]);
    $path = $this->priya->fresh()->photo_path;

    $this->delete(route('staff.destroy', $this->priya));

    Storage::disk('public')->assertMissing($path);
});

it('shows the photo on the booking page and the calendar', function () {
    $service = Service::factory()->for($this->business)->create();
    $service->staff()->attach($this->priya);
    $this->actingAs($this->owner)->post(route('staff.photo.store', $this->priya), ['photo' => UploadedFile::fake()->image('priya.jpg', 600, 600)]);
    $url = $this->priya->fresh()->photoUrl();

    $this->get(route('booking.show', ['business' => 'glow-studio']))
        ->assertInertia(fn (Assert $page) => $page->where('staff.0.photo_url', $url));

    $this->get(route('calendar', ['view' => 'day']))
        ->assertInertia(fn (Assert $page) => $page->where('staff.0.photo_url', $url));
});

it('is for owners, within their own business', function () {
    $staffUser = User::factory()->create();
    $this->business->addMember($staffUser, BusinessRole::Staff);
    $theirs = Staff::factory()->for(Business::factory())->create();

    $this->actingAs($staffUser)->post(route('staff.photo.store', $this->priya), ['photo' => UploadedFile::fake()->image('p.jpg', 600, 600)])->assertForbidden();
    $this->actingAs($this->owner)->post(route('staff.photo.store', $theirs->id), ['photo' => UploadedFile::fake()->image('p.jpg', 600, 600)])->assertNotFound();
    $this->actingAs($this->owner)->delete(route('staff.photo.destroy', $theirs->id))->assertNotFound();
});
