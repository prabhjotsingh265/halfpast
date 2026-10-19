@php
    $paid = $booking->deposit;
    $dueOnTheDay = $booking->price - $paid;
@endphp
<x-mail.layout :business="$business" :title="'You\'re booked at '.$business->name" :preheader="'See you '.$start->format('l j F').' at '.$start->format('g:i a').'.'" :reference="$booking->displayCode()" :footer-address="false">
    <tr>
        <td style="padding:20px 28px 8px;">
            <h1 class="hp-text" style="margin:0 0 4px;font-size:28px;line-height:1.2;color:#1B1D24;">You're booked.</h1>
            <p class="hp-muted" style="margin:0;font-size:16px;color:#5A5E69;">
                See you {{ $start->format('l j F') }} at {{ $start->format('g:i a') }}.
            </p>
        </td>
    </tr>
    <tr>
        <td style="padding:16px 28px;">
            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" class="hp-box" style="background:#F1EFEA;border-radius:12px;font-size:15px;">
                <tr>
                    <td class="hp-text" style="padding:16px 16px 4px;font-weight:600;font-size:17px;color:#1B1D24;" colspan="2">{{ $booking->service_name }}</td>
                </tr>
                @if ($booking->staff)
                    <tr>
                        <td class="hp-muted" style="padding:2px 16px;color:#5A5E69;" colspan="2">with {{ $booking->staff->name }}</td>
                    </tr>
                @endif
                <tr>
                    <td class="hp-muted" style="padding:12px 16px 4px;color:#5A5E69;vertical-align:top;">When</td>
                    <td class="hp-text" style="padding:12px 16px 4px;text-align:right;color:#1B1D24;">
                        {{ $start->format('D j M') }}, <span style="white-space:nowrap;">{{ $start->format('g:i') }}–{{ $end->format('g:i a') }}</span><br>
                        <span class="hp-muted" style="font-size:13px;color:#5A5E69;">{{ $zoneName }} time</span>
                    </td>
                </tr>
                @if ($business->address)
                    <tr>
                        <td class="hp-muted" style="padding:4px 16px;color:#5A5E69;vertical-align:top;">Where</td>
                        <td class="hp-text" style="padding:4px 16px;text-align:right;color:#1B1D24;">
                            {{ $business->address }}<br>
                            <a href="https://www.google.com/maps/search/?api=1&query={{ urlencode($business->address) }}" class="hp-link" style="font-size:13px;color:#A24E10;">Get directions</a>
                        </td>
                    </tr>
                @endif
                <tr>
                    <td class="hp-muted" style="padding:4px 16px;color:#5A5E69;">Price</td>
                    <td class="hp-text" style="padding:4px 16px;text-align:right;color:#1B1D24;">{{ $money($booking->price) }}</td>
                </tr>
                @if ($paid > 0)
                    <tr>
                        <td class="hp-muted" style="padding:4px 16px;color:#5A5E69;">Deposit paid</td>
                        <td class="hp-text" style="padding:4px 16px;text-align:right;color:#1B1D24;">{{ $money($paid) }}</td>
                    </tr>
                @endif
                <tr>
                    <td class="hp-text" style="padding:4px 16px 16px;font-weight:600;color:#1B1D24;">To pay on the day</td>
                    <td class="hp-text" style="padding:4px 16px 16px;text-align:right;font-weight:600;color:#1B1D24;">{{ $money($dueOnTheDay) }}</td>
                </tr>
            </table>
        </td>
    </tr>
    <tr>
        <td style="padding:8px 28px 28px;">
            <x-mail.button :href="$manageUrl">View or cancel your booking</x-mail.button>
            <p class="hp-muted" style="margin:16px 0 0;font-size:13px;color:#5A5E69;">
                Open the attached calendar file to add it to your calendar.
            </p>
        </td>
    </tr>
</x-mail.layout>
