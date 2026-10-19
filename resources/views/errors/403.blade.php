<x-error-page code="403" mode="blocked" title="This time is blocked out for you.">
    {{ $exception?->getMessage() && $exception->getMessage() !== 'This action is unauthorized.' ? $exception->getMessage() : 'This page belongs to someone else, or needs an owner of the business to open it.' }}

    <x-slot:actions>
        @auth
            <a href="{{ route('dashboard') }}">Go to your dashboard</a>
        @endauth
        <a href="{{ url('/') }}">Go to Halfpast</a>
    </x-slot:actions>
</x-error-page>
