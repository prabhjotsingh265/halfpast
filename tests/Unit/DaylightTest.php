<?php

use App\Support\Daylight;

it('matches the ramp in the design system at each fixed hour', function (float $hour, string $light, string $dark) {
    expect(Daylight::hex($hour))->toBe($light)
        ->and(Daylight::hex($hour, dark: true))->toBe($dark);
})->with([
    'dawn' => [6, '#FDE0DF', '#3F2222'],
    'morning' => [9, '#FBEEC9', '#342A0E'],
    'noon' => [12, '#D5F5FC', '#083138'],
    'afternoon' => [15, '#D5EBFC', '#142E3F'],
    'dusk' => [18, '#FED9C1', '#3D2514'],
    'evening' => [21, '#D6D3F4', '#2B2740'],
]);

it('blends between them, the short way round the colour wheel', function () {
    // Afternoon blue (240) to dusk apricot (55) passes through pink, not green.
    [, , $hue] = Daylight::oklch(16.5);

    expect($hue)->toBeGreaterThan(240)->toBeLessThan(360)
        ->and(Daylight::css(12))->toBe('oklch(0.950 0.035 210.0)');
});

it('holds the first and last colours outside opening hours', function () {
    expect(Daylight::hex(2))->toBe(Daylight::hex(6))
        ->and(Daylight::hex(23.5))->toBe(Daylight::hex(21));
});

it('fades evening back into dawn overnight, so a 24-hour dial has no seam', function () {
    expect(Daylight::aroundTheClock(21))->toBe(Daylight::oklch(21))
        ->and(Daylight::aroundTheClock(6))->toBe(Daylight::oklch(6))
        ->and(Daylight::aroundTheClock(0))->toEqual(Daylight::aroundTheClock(24))
        ->and(Daylight::dialGradient())->toStartWith('conic-gradient(oklch(')->toContain('360deg)');
});
