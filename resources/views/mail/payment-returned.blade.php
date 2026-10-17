<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light">
    <title>{{ $business->name }}</title>
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
                            <h1 style="margin:24px 0 8px;font-size:26px;line-height:1.25;">
                                @switch($outcome?->value)
                                    @case('slot_taken') Someone booked this time just before you. @break
                                    @case('duplicate') You paid twice, so one payment is coming back. @break
                                    @case('capture_failed') Your booking didn't go through. @break
                                    @default Your payment is coming back. @break
                                @endswitch
                            </h1>
                            <p style="margin:0;font-size:16px;color:#5A5E69;">
                                {{ $booking->service_name }}, {{ $start->format('l j F') }} at {{ $start->format('g:i a') }}.
                                @if ($outcome?->value === 'capture_failed')
                                    We couldn't take the deposit, so the time hasn't been kept for you.
                                @endif
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:16px 28px 0;">
                            <p style="margin:0;padding:14px 16px;background:#F1EFEA;border-radius:12px;font-size:15px;">
                                @if ($refunded)
                                    Your {{ $amount }} is being refunded in full to the way you paid. Refunds usually show within 5 to 7 working days.
                                @else
                                    You haven't been charged. The {{ $amount }} was only reserved, and your bank will release it, usually within a few days at most.
                                @endif
                            </p>
                        </td>
                    </tr>
                    @if (count($nearby) > 0)
                        <tr>
                            <td style="padding:16px 28px 0;font-size:15px;">
                                Still free the same day: <strong>{{ implode(', ', $nearby) }}</strong>.
                            </td>
                        </tr>
                    @endif
                    <tr>
                        <td style="padding:24px 28px 28px;">
                            @if ($manageUrl)
                                <a href="{{ $manageUrl }}" style="display:inline-block;background:#EE9433;color:#15181F;font-weight:600;font-size:15px;text-decoration:none;padding:12px 20px;border-radius:12px;">See your booking</a>
                            @else
                                <a href="{{ $bookAgainUrl }}" style="display:inline-block;background:#EE9433;color:#15181F;font-weight:600;font-size:15px;text-decoration:none;padding:12px 20px;border-radius:12px;">Pick another time</a>
                            @endif
                        </td>
                    </tr>
                </table>
                <p style="margin:16px 0 0;font-size:12px;color:#8A8D96;">Booked through Halfpast</p>
            </td>
        </tr>
    </table>
</body>
</html>
