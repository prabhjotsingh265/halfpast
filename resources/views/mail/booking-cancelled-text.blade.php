Your booking at {{ $business->name }} is cancelled.

{{ $booking->service_name }}@if ($booking->staff) with {{ $booking->staff->name }}@endif, {{ $start->format('l j F') }} at {{ $start->format('g:i a') }}.
@if ($byBusiness && $kept > 0)
{{ $business->name }} has cancelled it.
@elseif ($byBusiness)
{{ $business->name }} had to cancel it. Sorry for the trouble.
@endif
@if ($booking->deposit > 0)

@if ($booking->refund_due > 0)
Your {{ $money($booking->refund_due) }} deposit is being refunded to the way you paid. Refunds usually show within 5 to 7 working days.
@elseif ($byBusiness)
{{ $business->name }} has kept the {{ $money($kept) }} deposit. If you have a question about it, please get in touch with them.
@else
As this was less than {{ $business->cancellation_window_hours }} hours before the appointment, the {{ $money($kept) }} deposit is kept.
@endif
@endif

Book another time:
{{ $bookAgainUrl }}

Reference: {{ $booking->displayCode() }}
