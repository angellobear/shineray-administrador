<?php

namespace App\Enums;

enum ShipmentStatus: string
{
    case Pending = 'pending';
    case Created = 'created';
    case Failed = 'failed';
    case Canceled = 'canceled';
}
