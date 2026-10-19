@php
    $wait = (int) ($exception?->getHeaders()['Retry-After'] ?? 60);
    $when = $wait < 60 ? $wait.' '.str('second')->plural($wait) : intdiv($wait + 59, 60).' '.str('minute')->plural(intdiv($wait + 59, 60));
@endphp

<x-error-page code="429" mode="rush" title="That was a lot of tries at once.">
    Give it {{ $when }}, then try again.

    <x-slot:actions>
        <a href="{{ url()->current() }}">Try again</a>
        <a href="{{ url('/') }}">Go to Halfpast</a>
    </x-slot:actions>
</x-error-page>
