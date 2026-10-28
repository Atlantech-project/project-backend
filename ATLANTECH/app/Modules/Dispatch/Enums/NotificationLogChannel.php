<?php

namespace App\Modules\Dispatch\Enums;

/**
 * `notification_logs.channel`.
 */
enum NotificationLogChannel: string
{
    case Web = 'WEB';
    case Mobile = 'MOBILE';
    case Push = 'PUSH';
}
