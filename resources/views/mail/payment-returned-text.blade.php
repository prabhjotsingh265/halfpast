@switch($outcome?->value)
@case('slot_taken')
Someone booked this time just before you.
@break
@case('duplicate')
You paid twice, so one payment is coming back.
@break
@case('capture_failed')
Your booking didn't go through.
@break
@default
Your payment is coming back.
@endswitch

{{ $booking->service_name }}, {{ $start->format('l j F') }} at {{ $start->format('g:i a') }}, at {{ $business->name }}.
@if ($outcome?->value === 'capture_failed')
We couldn't take the deposit, so the time hasn't been kept for you.
@endif

@if ($refunded)
Your {{ $amount }} is being refunded in full to the way you paid. Refunds usually show within 5 to 7 working days.
@else
You haven't been charged. The {{ $amount }} was only reserved, and your bank will release it, usually within a few days at most.
@endif
@if (count($nearby) > 0)

Still free the same day: {{ implode(', ', $nearby) }}.
@endif

{{ $manageUrl ? 'See your booking:' : 'Pick another time:' }}
{{ $manageUrl ?? $bookAgainUrl }}

Booked through Halfpast
