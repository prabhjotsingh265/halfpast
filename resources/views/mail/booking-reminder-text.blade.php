{{ $soon ? 'See you in 2 hours.' : 'See you tomorrow.' }}

{{ $booking->service_name }}@if ($booking->staff) with {{ $booking->staff->name }}@endif at {{ $business->name }}.
{{ $start->format('l j F') }}, {{ $start->format('g:i') }}–{{ $end->format('g:i a') }} ({{ $zoneName }} time)
@if ($business->address)
{{ $business->address }}
@endif
To pay on the day: {{ $dueOnTheDay }}
Reference: {{ $booking->displayCode() }}

{{ $canCancel ? 'View or cancel:' : 'View your booking:' }}
{{ $manageUrl }}

Booked through Halfpast
