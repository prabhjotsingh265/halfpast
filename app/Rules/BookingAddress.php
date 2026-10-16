<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Facades\Route;

/**
 * A business's booking address: the part after the slash in
 * halfpast.example.com/glow-studio. It can't clash with the app's own pages.
 */
class BookingAddress implements ValidationRule
{
    /**
     * Names kept back even though no route uses them yet.
     */
    private const RESERVED = [
        'about', 'admin', 'api', 'app', 'assets', 'billing', 'blog', 'book',
        'build', 'contact', 'demo', 'docs', 'features', 'help', 'pricing',
        'privacy', 'status', 'support', 'terms', 'webhooks', 'www',
    ];

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || ! preg_match('/^[a-z0-9](?:[a-z0-9-]{1,38}[a-z0-9])$/', $value)) {
            $fail('Use 3 to 40 lowercase letters, numbers or hyphens, starting and ending with a letter or number.');

            return;
        }

        if (str_contains($value, '--')) {
            $fail("A booking address can't contain two hyphens in a row.");

            return;
        }

        if (in_array($value, self::reserved(), true)) {
            $fail('That address is used by Halfpast itself. Try another.');
        }
    }

    /**
     * The reserved names, plus the first part of every route the app has.
     *
     * @return list<string>
     */
    public static function reserved(): array
    {
        $fromRoutes = collect(Route::getRoutes()->getRoutes())
            ->map(fn ($route) => strtolower(explode('/', trim($route->uri(), '/'))[0]))
            ->filter(fn (string $segment) => $segment !== '' && ! str_starts_with($segment, '{'));

        return array_values($fromRoutes->merge(self::RESERVED)->unique()->all());
    }
}
