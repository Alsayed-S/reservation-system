<?php

namespace App\Enum;

enum ReservationAction: string
{
    case CREATED = 'created';
    case CONFIRMED = 'confirmed';
    case CANCELLED = 'cancelled';
    case EXPIRED = 'expired';
    case UPDATED = 'updated';
}