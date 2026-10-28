<?php

namespace App\Modules\Access\Enums;

/**
 * `user_devices.platform`.
 */
enum DevicePlatform: string
{
    case Android = 'ANDROID';
    case Ios = 'IOS';
}
