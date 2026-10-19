<x-error-page :code="$exception?->getStatusCode() ?? 500" mode="stuck" title="Something broke on our side.">
    It isn't anything you did, and it's been noted. Try again in a minute.

    <x-slot:actions>
        <a href="{{ url()->current() }}">Try again</a>
        <a href="{{ url('/') }}">Go to Halfpast</a>
    </x-slot:actions>
</x-error-page>
