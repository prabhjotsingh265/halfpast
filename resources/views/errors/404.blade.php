@php
    // One plain segment, like /glow-studio, is someone looking for a booking page.
    $path = request()->path();
    $address = preg_match('/^[a-z0-9-]+$/', $path) ? $path : null;
@endphp

<x-error-page code="404" mode="sweep" :title="$address ? 'Nothing is booked at this address.' : 'This page isn\'t here.'">
    @if ($address)
        There's no booking page at <code>/{{ $address }}</code>. Check the link you were sent, or ask the business for its address.
    @else
        It may have moved, or the link has a typo in it.
    @endif
</x-error-page>
