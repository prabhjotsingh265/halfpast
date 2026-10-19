@php
    $headline = match ($outcome?->value) {
        'slot_taken' => 'Someone booked this time just before you.',
        'duplicate' => 'You paid twice, so one payment is coming back.',
        'capture_failed' => "Your booking didn't go through.",
        default => 'Your payment is coming back.',
    };
@endphp
<x-mail.layout :business="$business" :title="$business->name" :preheader="$headline">
    <tr>
        <td style="padding:20px 28px 8px;">
            <h1 class="hp-text" style="margin:0 0 8px;font-size:26px;line-height:1.25;color:#1B1D24;">{{ $headline }}</h1>
            <p class="hp-muted" style="margin:0;font-size:16px;color:#5A5E69;">
                {{ $booking->service_name }}, {{ $start->format('l j F') }} at {{ $start->format('g:i a') }}.
                @if ($outcome?->value === 'capture_failed')
                    We couldn't take the deposit, so the time hasn't been kept for you.
                @endif
            </p>
        </td>
    </tr>
    <x-mail.note>
        @if ($refunded)
            Your {{ $amount }} is being refunded in full to the way you paid. Refunds usually show within 5 to 7 working days.
        @else
            You haven't been charged. The {{ $amount }} was only reserved, and your bank will release it, usually within a few days at most.
        @endif
    </x-mail.note>
    @if (count($nearby) > 0)
        <tr>
            <td class="hp-text" style="padding:16px 28px 0;font-size:15px;color:#1B1D24;">
                Still free the same day: <strong>{{ implode(', ', $nearby) }}</strong>.
            </td>
        </tr>
    @endif
    <tr>
        <td style="padding:24px 28px 28px;">
            @if ($manageUrl)
                <x-mail.button :href="$manageUrl">See your booking</x-mail.button>
            @else
                <x-mail.button :href="$bookAgainUrl">Pick another time</x-mail.button>
            @endif
        </td>
    </tr>
</x-mail.layout>
