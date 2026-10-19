<x-error-page code="500" mode="stuck" title="Something broke on our side.">
    It isn't anything you did, and it's been noted. Try again in a minute. If you were paying, check your email first: a confirmation means the booking went through.

    <x-slot:actions>
        <a href="{{ url()->current() }}">Try again</a>
        <a href="{{ url('/') }}">Go to Halfpast</a>
    </x-slot:actions>
</x-error-page>
