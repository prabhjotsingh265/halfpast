<x-mail.layout :business="$business" :title="'Your booking at '.$business->name.' is cancelled'" :preheader="$booking->service_name.', '.$start->format('l j F').' at '.$start->format('g:i a').'.'" :reference="$booking->displayCode()">
    <tr>
        <td style="padding:20px 28px 8px;">
            <h1 class="hp-text" style="margin:0 0 4px;font-size:28px;line-height:1.2;color:#1B1D24;">Your booking is cancelled.</h1>
            <p class="hp-muted" style="margin:0;font-size:16px;color:#5A5E69;">
                {{ $booking->service_name }}@if ($booking->staff) with {{ $booking->staff->name }}@endif,
                {{ $start->format('l j F') }} at {{ $start->format('g:i a') }}.
                @if ($byBusiness && $kept > 0)
                    {{ $business->name }} has cancelled it.
                @elseif ($byBusiness)
                    {{ $business->name }} had to cancel it. Sorry for the trouble.
                @endif
            </p>
        </td>
    </tr>
    @if ($booking->deposit > 0)
        <x-mail.note>
            @if ($booking->refund_due > 0)
                Your {{ $money($booking->refund_due) }} deposit is being refunded to the way you paid. Refunds usually show within 5 to 7 working days.
            @elseif ($byBusiness)
                {{ $business->name }} has kept the {{ $money($kept) }} deposit. If you have a question about it, please get in touch with them.
            @else
                As this was less than {{ $business->cancellation_window_hours }} hours before the appointment, the {{ $money($kept) }} deposit is kept.
            @endif
        </x-mail.note>
    @endif
    <tr>
        <td style="padding:24px 28px 28px;">
            <x-mail.button :href="$bookAgainUrl">Book another time</x-mail.button>
        </td>
    </tr>
</x-mail.layout>
