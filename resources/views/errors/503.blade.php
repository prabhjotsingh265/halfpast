{{-- Also shown during maintenance: php artisan down --render="errors::503" --}}
<x-error-page code="503" mode="filling" title="Back in a few minutes.">
    Halfpast is being updated. Bookings already made are safe, and reminders will still go out. Try again shortly.

    <x-slot:actions>
        <a href="{{ url()->current() }}">Try again</a>
    </x-slot:actions>
</x-error-page>
