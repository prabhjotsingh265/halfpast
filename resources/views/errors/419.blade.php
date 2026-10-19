<x-error-page code="419" mode="drained" title="The page sat open a little too long.">
    For your safety, forms stop working after a while. Go back, refresh the page and try again; you may need to type a thing or two again.

    <x-slot:actions>
        <a href="{{ url()->previous() }}">Go back</a>
        <a href="{{ url('/') }}">Go to Halfpast</a>
    </x-slot:actions>
</x-error-page>
