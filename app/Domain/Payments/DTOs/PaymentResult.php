<?php

namespace App\Domain\Payments\DTOs;

use App\Enums\PaymentStatus;

final readonly class PaymentResult
{
    /**
     * @param  array<string, mixed>  $raw  Respuesta cruda del gateway para auditoría.
     */
    public function __construct(
        public PaymentStatus $status,
        public ?string $gatewayReference = null,
        public ?string $responseCode = null,
        public ?string $message = null,
        public array $raw = [],
    ) {}

    /** @param  array<string, mixed>  $raw */
    public static function authorized(string $gatewayReference, ?string $responseCode = null, array $raw = []): self
    {
        return new self(PaymentStatus::Authorized, $gatewayReference, $responseCode, null, $raw);
    }

    /** @param  array<string, mixed>  $raw */
    public static function failed(?string $responseCode = null, ?string $message = null, array $raw = []): self
    {
        return new self(PaymentStatus::Failed, null, $responseCode, $message, $raw);
    }

    public function isSuccessful(): bool
    {
        return $this->status->isSuccessful();
    }
}
