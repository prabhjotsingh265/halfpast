<?php

namespace App\Payments;

use App\Models\Business;
use App\Payments\Gateways\TestGateway;
use Illuminate\Contracts\Container\Container;
use InvalidArgumentException;

/**
 * Every gateway the app knows, and which of them a business can offer.
 */
class Gateways
{
    /** @var array<string, class-string<PaymentGateway>> */
    private const DRIVERS = [
        'test' => TestGateway::class,
    ];

    public function __construct(private Container $container) {}

    public function get(string $key): PaymentGateway
    {
        $class = self::DRIVERS[$key] ?? throw new InvalidArgumentException("There's no payment gateway called {$key}.");

        return $this->container->make($class);
    }

    /**
     * Every gateway, set up or not.
     *
     * @return array<string, PaymentGateway>
     */
    public function all(): array
    {
        $gateways = [];

        foreach (array_keys(self::DRIVERS) as $key) {
            $gateways[$key] = $this->get($key);
        }

        return $gateways;
    }

    /**
     * Gateways whose keys are set, so they can be switched on.
     *
     * @return array<string, PaymentGateway>
     */
    public function configured(): array
    {
        return array_filter($this->all(), fn (PaymentGateway $gateway) => $gateway->isConfigured());
    }

    /**
     * What a business's customers can pay with: switched on by the owner,
     * set up, and able to take the business's currency.
     *
     * @return array<string, PaymentGateway>
     */
    public function for(Business $business): array
    {
        return array_filter(
            $this->configured(),
            fn (PaymentGateway $gateway) => in_array($gateway->key(), $business->payment_gateways, true)
                && $gateway->supports($business->currency),
        );
    }

    public function canTakeDeposits(Business $business): bool
    {
        return $this->for($business) !== [];
    }
}
