<?php

namespace App\Enums;

enum OrderStatus: string
{
    case Pending = 'pending';
    case Paid = 'paid';
    case Fulfilled = 'fulfilled';
    case Canceled = 'canceled';
    case Refunded = 'refunded';

    /**
     * Transiciones permitidas. Una orden pagada nunca vuelve a pending; una
     * cancelada o reembolsada es terminal.
     *
     * @return list<self>
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Pending => [self::Paid, self::Canceled],
            self::Paid => [self::Fulfilled, self::Refunded, self::Canceled],
            self::Fulfilled => [self::Refunded],
            self::Canceled, self::Refunded => [],
        };
    }

    public function canTransitionTo(self $target): bool
    {
        return in_array($target, $this->allowedTransitions(), true);
    }

    public function isTerminal(): bool
    {
        return $this->allowedTransitions() === [];
    }
}
