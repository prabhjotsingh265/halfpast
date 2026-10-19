<x-mail.layout :business="$business" :title="($soon ? 'In 2 hours: ' : 'Tomorrow: ').$booking->service_name.' at '.$business->name" :preheader="$booking->service_name.' at '.$start->format('g:i a').'.'" :reference="$booking->displayCode()" :footer-address="false">
    <tr>
        <td style="padding:20px 28px 8px;">
            <h1 class="hp-text" style="margin:0 0 4px;font-size:28px;line-height:1.2;color:#1B1D24;">
                {{ $soon ? 'See you in 2 hours.' : 'See you tomorrow.' }}
            </h1>
            <p class="hp-muted" style="margin:0;font-size:16px;color:#5A5E69;">
                {{ $booking->service_name }}@if ($booking->staff) with {{ $booking->staff->name }}@endif at {{ $start->format('g:i a') }}.
            </p>
        </td>
    </tr>
    <tr>
        <td style="padding:16px 28px;">
            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" class="hp-box" style="background:#F1EFEA;border-radius:12px;font-size:15px;">
                <tr>
                    <td class="hp-muted" style="padding:14px 16px 4px;color:#5A5E69;vertical-align:top;">When</td>
                    <td class="hp-text" style="padding:14px 16px 4px;text-align:right;color:#1B1D24;">
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
                    <td class="hp-muted" style="padding:4px 16px 14px;color:#5A5E69;">To pay on the day</td>
                    <td class="hp-text" style="padding:4px 16px 14px;text-align:right;font-weight:600;color:#1B1D24;">{{ $dueOnTheDay }}</td>
                </tr>
            </table>
        </td>
    </tr>
    <tr>
        <td style="padding:8px 28px 28px;">
            <x-mail.button :href="$manageUrl">{{ $canCancel ? 'View or cancel' : 'View your booking' }}</x-mail.button>
        </td>
    </tr>
</x-mail.layout>
