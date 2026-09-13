<?php

namespace App\Domain\ErpSync\DTOs;

final readonly class InvoiceResult
{
    /**
     * @param  array<string, mixed>  $raw
     */
    public function __construct(
        public bool $success,
        public ?string $invoiceNumber = null,
        public ?string $message = null,
        public array $raw = [],
    ) {}
}
