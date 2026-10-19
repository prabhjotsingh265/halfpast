You're booked at {{ $business->name }}.

{{ $booking->service_name }}@if ($booking->staff) with {{ $booking->staff->name }}@endif

{{ $start->format('l j F') }}, {{ $start->format('g:i') }}–{{ $end->format('g:i a') }} ({{ $zoneName }} time)
@if ($business->address)
{{ $business->address }}
@endif

Price: {{ $money($booking->price) }}
@if ($booking->deposit > 0)
Deposit paid: {{ $money($booking->deposit) }}
@endif
To pay on the day: {{ $money($booking->price - $booking->deposit) }}
Reference: {{ $booking->displayCode() }}

View or cancel your booking:
{{ $manageUrl }}

Open the attached calendar file to add it to your calendar.
@if ($business->phone)

Questions? Call {{ $business->name }} on {{ $business->phone }}.
@endif

Booked through Halfpast
