@props([
    'business',
    'title',
    // The line inbox lists show after the subject.
    'preheader' => null,
    'reference' => null,
    // Off where the body already says where, in a "Where" row.
    'footerAddress' => true,
])

@php
    $logo = $business->logoUrl();
    $directions = $business->address ? 'https://www.google.com/maps/search/?api=1&query='.urlencode($business->address) : null;
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light dark">
    <meta name="supported-color-schemes" content="light dark">
    <title>{{ $title }}</title>
    {{--
        Colours are written on each element for clients that drop <style>; the
        dark versions below are for the ones that follow the reader's theme
        (Apple Mail, Outlook, Thunderbird). Classes are prefixed so a client's
        own styles can't clash with them.
    --}}
    <style>
        :root { color-scheme: light dark; supported-color-schemes: light dark; }
        @media (prefers-color-scheme: dark) {
            .hp-body { background: #0F1217 !important; }
            .hp-card { background: #1A1D24 !important; border-color: #2A2E36 !important; }
            .hp-box { background: #23262E !important; }
            .hp-text { color: #F0EEEA !important; }
            .hp-muted { color: #A9ADB5 !important; }
            .hp-faint { color: #7D818A !important; }
            .hp-rule { border-color: #2A2E36 !important; }
            .hp-link { color: #F4A65A !important; }
        }
        [data-ogsc] .hp-body { background: #0F1217 !important; }
        [data-ogsc] .hp-card { background: #1A1D24 !important; border-color: #2A2E36 !important; }
        [data-ogsc] .hp-box { background: #23262E !important; }
        [data-ogsc] .hp-text { color: #F0EEEA !important; }
        [data-ogsc] .hp-muted { color: #A9ADB5 !important; }
        [data-ogsc] .hp-link { color: #F4A65A !important; }
    </style>
</head>
<body class="hp-body" style="margin:0;padding:0;background:#F4F2EC;color:#1B1D24;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif;">
    @if ($preheader)
        <div style="display:none;max-height:0;max-width:0;overflow:hidden;opacity:0;mso-hide:all;">{{ $preheader }}</div>
    @endif
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" class="hp-body" style="background:#F4F2EC;">
        <tr>
            <td align="center" style="padding:32px 16px;">
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" class="hp-card" style="max-width:520px;background:#FBFAF7;border:1px solid #E2E3E8;border-radius:16px;">
                    <tr>
                        <td style="padding:28px 28px 4px;">
                            <table role="presentation" cellpadding="0" cellspacing="0">
                                <tr>
                                    @if ($logo)
                                        {{-- On white in both themes, so a dark logo still shows. --}}
                                        <td style="background:#FFFFFF;border-radius:12px;padding:6px;line-height:0;">
                                            <img src="{{ $logo }}" alt="{{ $business->name }}" height="36" style="display:block;height:36px;width:auto;max-width:150px;border:0;">
                                        </td>
                                    @else
                                        <td style="width:44px;height:44px;border-radius:12px;background:#EE9433;color:#15181F;font-size:21px;font-weight:800;text-align:center;vertical-align:middle;">{{ mb_strtoupper(mb_substr($business->name, 0, 1)) }}</td>
                                    @endif
                                    <td class="hp-text" style="padding-left:12px;font-size:15px;font-weight:600;color:#1B1D24;">{{ $business->name }}</td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    {{ $slot }}

                    @if (($footerAddress && $business->address) || $business->phone)
                        <tr>
                            <td style="padding:0 28px 24px;">
                                <p class="hp-muted hp-rule" style="margin:0;padding-top:16px;border-top:1px solid #E2E3E8;font-size:13px;line-height:1.6;color:#5A5E69;">
                                    @if ($footerAddress && $business->address)
                                        {{ $business->address }} · <a href="{{ $directions }}" class="hp-link" style="color:#A24E10;">Directions</a><br>
                                    @endif
                                    @if ($business->phone)
                                        Questions? Call <a href="tel:{{ preg_replace('/[^0-9+]/', '', $business->phone) }}" class="hp-link" style="color:#A24E10;">{{ $business->phone }}</a>
                                    @endif
                                </p>
                            </td>
                        </tr>
                    @endif
                </table>
                <p class="hp-faint" style="margin:16px 0 0;font-size:12px;color:#8A8D96;">
                    Booked through Halfpast
                    @if ($reference)
                        · Reference {{ $reference }}
                    @endif
                </p>
            </td>
        </tr>
    </table>
</body>
</html>
