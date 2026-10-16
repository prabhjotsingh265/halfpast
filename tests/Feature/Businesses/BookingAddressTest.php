<?php

use App\Rules\BookingAddress;
use Illuminate\Support\Facades\Validator;

function bookingAddressPasses(string $value): bool
{
    return Validator::make(['slug' => $value], ['slug' => [new BookingAddress]])->passes();
}

it('accepts plain lowercase addresses', function (string $slug) {
    expect(bookingAddressPasses($slug))->toBeTrue();
})->with(['glow-studio', 'ben-s-barbers', 'northside-physio', 'studio9', 'abc']);

it('rejects addresses that are badly formed', function (string $slug) {
    expect(bookingAddressPasses($slug))->toBeFalse();
})->with([
    'too short' => 'ab',
    'capitals' => 'Glow-Studio',
    'spaces' => 'glow studio',
    'leading hyphen' => '-glow',
    'trailing hyphen' => 'glow-',
    'double hyphen' => 'glow--studio',
    'too long' => str_repeat('a', 41),
    'symbols' => 'glow_studio!',
]);

it('rejects addresses the app uses for its own pages', function (string $slug) {
    expect(bookingAddressPasses($slug))->toBeFalse();
})->with(['login', 'register', 'dashboard', 'settings', 'admin', 'pricing', 'webhooks', 'up']);
