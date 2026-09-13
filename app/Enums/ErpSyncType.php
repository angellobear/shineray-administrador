<?php

namespace App\Enums;

enum ErpSyncType: string
{
    case Products = 'products';
    case Images = 'images';
    case ClientsB2b = 'clients_b2b';
    case PoliciesB2b = 'policies_b2b';
    case TransportistasB2b = 'transportistas_b2b';
}
