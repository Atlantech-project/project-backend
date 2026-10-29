<?php

namespace App\Modules\Yard\Enums;

/**
 * `yard_slots.status`.
 */
enum YardSlotStatus: string
{
    case Active = 'ACTIVE';
    case Blocked = 'BLOCKED';
}
