<?php

namespace App\Enums;

enum DiscountType: string
{
    /** `value` es un porcentaje entero (0-100). */
    case Percentage = 'percentage';

    /** `value` es un monto en centavos. */
    case Fixed = 'fixed';

    /** `value` se ignora; anula el costo de envío. */
    case FreeShipping = 'free_shipping';
}
