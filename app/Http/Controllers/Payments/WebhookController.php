<?php

namespace App\Http\Controllers\Payments;

use App\Actions\Payments\ReceiveWebhook;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class WebhookController extends Controller
{
    public function __invoke(Request $request, string $gateway, ReceiveWebhook $receive): Response
    {
        return response('', $receive->handle($gateway, $request));
    }
}
