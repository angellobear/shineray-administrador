<?php

namespace App\Enums;

enum PaymentGateway: string
{
    case Datafast = 'datafast';
    case DatafastB2b = 'datafast_b2b';
    case Deuna = 'deuna';
    case DeunaB2b = 'deuna_b2b';
    case CreditoB2b = 'credito_b2b';

    public function isB2b(): bool
    {
        return match ($this) {
            self::DatafastB2b, self::DeunaB2b, self::CreditoB2b => true,
            self::Datafast, self::Deuna => false,
        };
    }

    /**
     * Crédito B2B no pasa por una pasarela externa: se aprueba contra la
     * política de crédito del cliente y se factura directo en el ERP.
     */
    public function usesExternalProcessor(): bool
    {
        return $this !== self::CreditoB2b;
    }
}
