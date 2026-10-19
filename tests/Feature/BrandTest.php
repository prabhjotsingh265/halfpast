<?php

it('links the icons and the web app manifest from every page', function () {
    $this->get('/')
        ->assertSee('<link rel="icon" href="/favicon.svg" type="image/svg+xml">', false)
        ->assertSee('<link rel="manifest" href="/site.webmanifest">', false);
});

it('ships a manifest whose icons are all there', function () {
    $manifest = json_decode((string) file_get_contents(public_path('site.webmanifest')), true, flags: JSON_THROW_ON_ERROR);

    expect($manifest['name'])->toBe('Halfpast')
        ->and($manifest['icons'])->not->toBeEmpty();

    foreach ($manifest['icons'] as $icon) {
        [$width, $height] = getimagesize(public_path(ltrim($icon['src'], '/'))) ?: [0, 0];

        expect("{$width}x{$height}")->toBe($icon['sizes']);
    }
});

it('uses the Halfpast mark for the favicon, not the framework logo', function () {
    expect(file_get_contents(public_path('favicon.svg')))->toContain('viewBox="0 0 32 32"')
        ->and(getimagesize(public_path('apple-touch-icon.png'))[0] ?? 0)->toBe(180);
});
