<?php

namespace App\Modules\Dispatch\Enums;

/**
 * `notification_logs.status`.
 */
enum NotificationLogStatus: string
{
    case Sent = 'SENT';
    case Failed = 'FAILED';
    case Acknowledged = 'ACKNOWLEDGED';
}
