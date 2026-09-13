<?php

namespace App\Domain\Payments\Services;

use App\Domain\Payments\Contracts\PaymentGatewayContract;
use App\Enums\PaymentGateway;
use Illuminate\Contracts\Container\Container;
use InvalidArgumentException;

/**
 * Resuelve la implementación concreta de `PaymentGatewayContract` para un
 * gateway dado. El mapa enum → clase se declara en `IntegrationsServiceProvider`.
 */
final class PaymentGatewayResolver
{
    /**
     * @param  array<string, class-string<PaymentGatewayContract>|\Closure(): PaymentGatewayContract>  $bindings
     */
    public function __construct(
        private readonly Container $container,
        private readonly array $bindings,
    ) {}

    public function resolve(PaymentGateway $gateway): PaymentGatewayContract
    {
        $binding = $this->bindings[$gateway->value] ?? null;

        if ($binding === null) {
            throw new InvalidArgumentException(sprintf('No hay gateway registrado para "%s".', $gateway->value));
        }

        $instance = $binding instanceof \Closure ? $binding() : $this->container->make($binding);

        if (! $instance instanceof PaymentGatewayContract) {
            throw new InvalidArgumentException(sprintf('El binding de "%s" no implementa PaymentGatewayContract.', $gateway->value));
        }

        return $instance;
    }

    /** @return list<PaymentGateway> */
    public function registered(): array
    {
        return array_map(
            fn (string $value): PaymentGateway => PaymentGateway::from($value),
            array_keys($this->bindings),
        );
    }
}
