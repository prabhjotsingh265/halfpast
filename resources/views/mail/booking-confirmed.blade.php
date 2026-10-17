@php
    $paid = $booking->deposit;
    $dueOnTheDay = $booking->price - $paid;
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light">
    <title>You're booked at {{ $business->name }}</title>
</head>
<body style="margin:0;padding:0;background:#F4F2EC;color:#1B1D24;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#F4F2EC;">
        <tr>
            <td align="center" style="padding:32px 16px;">
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:520px;background:#FBFAF7;border:1px solid #E2E3E8;border-radius:16px;">
                    <tr>
                        <td style="padding:28px 28px 8px;">
                            <table role="presentation" cellpadding="0" cellspacing="0">
                                <tr>
                                    <td style="width:40px;height:40px;border-radius:12px;background:#EE9433;color:#15181F;font-size:20px;font-weight:800;text-align:center;vertical-align:middle;">
                                        {{ mb_strtoupper(mb_substr($business->name, 0, 1)) }}
                                    </td>
                                    <td style="padding-left:12px;font-size:14px;color:#5A5E69;">{{ $business->name }}</td>
                                </tr>
                            </table>
                            <h1 style="margin:24px 0 4px;font-size:28px;line-height:1.2;">You're booked.</h1>
                            <p style="margin:0;font-size:16px;color:#5A5E69;">
                                See you {{ $start->format('l j F') }} at {{ $start->format('g:i a') }}.
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:16px 28px;">
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#F1EFEA;border-radius:12px;font-size:15px;">
                                <tr>
                                    <td style="padding:16px 16px 4px;font-weight:600;font-size:17px;" colspan="2">{{ $booking->service_name }}</td>
                                </tr>
                                @if ($booking->staff)
                                    <tr>
                                        <td style="padding:2px 16px;color:#5A5E69;" colspan="2">with {{ $booking->staff->name }}</td>
                                    </tr>
                                @endif
                                <tr>
                                    <td style="padding:12px 16px 4px;color:#5A5E69;">When</td>
                                    <td style="padding:12px 16px 4px;text-align:right;">
                                        {{ $start->format('D j M') }}, <span style="white-space:nowrap;">{{ $start->format('g:i') }}–{{ $end->format('g:i a') }}</span><br>
                                        <span style="font-size:13px;color:#5A5E69;">{{ $zoneName }} time</span>
                                    </td>
                                </tr>
                                <tr>
                                    <td style="padding:4px 16px;color:#5A5E69;">Price</td>
                                    <td style="padding:4px 16px;text-align:right;">{{ $money($booking->price) }}</td>
                                </tr>
                                @if ($paid > 0)
                                    <tr>
                                        <td style="padding:4px 16px;color:#5A5E69;">Deposit paid</td>
                                        <td style="padding:4px 16px;text-align:right;">{{ $money($paid) }}</td>
                                    </tr>
                                @endif
                                <tr>
                                    <td style="padding:4px 16px;font-weight:600;">To pay on the day</td>
                                    <td style="padding:4px 16px;text-align:right;font-weight:600;">{{ $money($dueOnTheDay) }}</td>
                                </tr>
                                <tr>
                                    <td style="padding:4px 16px 16px;color:#5A5E69;">Reference</td>
                                    <td style="padding:4px 16px 16px;text-align:right;font-family:Menlo,Consolas,monospace;">{{ $booking->displayCode() }}</td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:8px 28px 28px;">
                            <a href="{{ $manageUrl }}" style="display:inline-block;background:#EE9433;color:#15181F;font-weight:600;font-size:15px;text-decoration:none;padding:12px 20px;border-radius:12px;">View or cancel your booking</a>
                            <p style="margin:16px 0 0;font-size:13px;color:#5A5E69;">
                                Open the attached calendar file to add it to your calendar.
                            </p>
                        </td>
                    </tr>
                </table>
                <p style="margin:16px 0 0;font-size:12px;color:#8A8D96;">Booked through Halfpast</p>
            </td>
        </tr>
    </table>
</body>
</html>
