<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light">
    <title>Your booking at {{ $business->name }} is cancelled</title>
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
                            <h1 style="margin:24px 0 4px;font-size:28px;line-height:1.2;">Your booking is cancelled.</h1>
                            <p style="margin:0;font-size:16px;color:#5A5E69;">
                                {{ $booking->service_name }}@if ($booking->staff) with {{ $booking->staff->name }}@endif,
                                {{ $start->format('l j F') }} at {{ $start->format('g:i a') }}.
                                @if ($byBusiness)
                                    {{ $business->name }} had to cancel it. Sorry for the trouble.
                                @endif
                            </p>
                        </td>
                    </tr>
                    @if ($booking->deposit > 0)
                        <tr>
                            <td style="padding:16px 28px 0;">
                                <p style="margin:0;padding:14px 16px;background:#F1EFEA;border-radius:12px;font-size:15px;">
                                    @if ($booking->refund_due > 0)
                                        Your {{ $money($booking->refund_due) }} deposit is being refunded to the way you paid. Refunds usually show within 5 to 7 working days.
                                    @else
                                        As this was less than {{ $business->cancellation_window_hours }} hours before the appointment, the {{ $money($kept) }} deposit is kept.
                                    @endif
                                </p>
                            </td>
                        </tr>
                    @endif
                    <tr>
                        <td style="padding:24px 28px 28px;">
                            <a href="{{ $bookAgainUrl }}" style="display:inline-block;background:#EE9433;color:#15181F;font-weight:600;font-size:15px;text-decoration:none;padding:12px 20px;border-radius:12px;">Book another time</a>
                        </td>
                    </tr>
                </table>
                <p style="margin:16px 0 0;font-size:12px;color:#8A8D96;">Booked through Halfpast · Reference {{ $booking->displayCode() }}</p>
            </td>
        </tr>
    </table>
</body>
</html>
