<?php

namespace App\Support;

/**
 * Único punto de cálculo de IVA del sistema. Todos los montos son enteros en
 * centavos; los redondeos siguen la convención actual (`Math.round`).
 */
final readonly class TaxCalculator
{
    public function __construct(private float $rate) {}

    public static function fromConfig(): self
    {
        return new self((float) config('shineray.iva_rate'));
    }

    public function rate(): float
    {
        return $this->rate;
    }

    /** Multiplicador bruto/neto (ej. 1.15 para 15%). */
    public function grossFactor(): float
    {
        return 1 + $this->rate;
    }

    /** Quita el IVA a un monto que ya lo incluye (precio del ERP → precio base). */
    public function netFromGross(int $grossCents): int
    {
        return (int) round($grossCents / $this->grossFactor());
    }

    /** IVA contenido en un monto bruto. */
    public function taxFromGross(int $grossCents): int
    {
        return $grossCents - $this->netFromGross($grossCents);
    }

    /** IVA a agregar sobre un monto neto. */
    public function taxFromNet(int $netCents): int
    {
        return (int) round($netCents * $this->rate);
    }

    public function grossFromNet(int $netCents): int
    {
        return $netCents + $this->taxFromNet($netCents);
    }

    /**
     * Convierte un precio del ERP (dólares con IVA incluido, ej. "12.50") a
     * centavos sin IVA. Equivale al `Math.round((PRECIO / 1.15) * 100)` actual.
     */
    public function erpGrossPriceToNetCents(float|string $grossPrice): int
    {
        return (int) round(((float) $grossPrice / $this->grossFactor()) * 100);
    }
}
