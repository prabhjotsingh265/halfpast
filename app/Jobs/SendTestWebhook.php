<?php

namespace App\Jobs;

use App\Actions\Payments\ReceiveWebhook;
use App\Payments\Gateways\TestGateway;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Http\Request;

/**
 * The test gateway telling us about a payment, a moment after it happens,
 * the way a real gateway's webhook does. It goes through the same signature
 * check and processing as a real one.
 */
class SendTestWebhook implements ShouldQueue
{
    use Queueable;

    public function __construct(public string $order, public string $type) {}

    public function handle(TestGateway $gateway, ReceiveWebhook $receive): void
    {
        [$body, $signature] = $gateway->signedEvent($this->order, $this->type);

        $receive->handle('test', Request::create('/webhooks/test', 'POST', server: [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_TEST_SIGNATURE' => $signature,
        ], content: $body));
    }
}
