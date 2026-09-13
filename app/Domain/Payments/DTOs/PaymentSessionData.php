<?php

namespace App\Domain\Payments\DTOs;

/**
 * Resultado de iniciar una sesión de pago con el gateway (checkoutId de
 * OPPWa, id de DeUna, etc.). Lo que el storefront necesita para continuar.
 */
final readonly class PaymentSessionData
{
    /**
     * @param  array<string, mixed>  $data  Datos que el frontend necesita para renderizar el widget/redirigir.
     * @param  array<string, mixed>  $raw  Respuesta cruda del gateway, solo para auditoría (`payments.raw_response`).
     */
    public function __construct(
        public string $gatewayReference,
        public array $data = [],
        public ?string $redirectUrl = null,
        public array $raw = [],
    ) {}
}
