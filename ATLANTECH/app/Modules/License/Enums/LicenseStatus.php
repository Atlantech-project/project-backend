<?php

namespace App\Modules\License\Enums;

/**
 * `license_states.license_status`.
 */
enum LicenseStatus: string
{
    case Pending = 'PENDING';
    case Active = 'ACTIVE';
    case Suspended = 'SUSPENDED';
    case Expired = 'EXPIRED';
    case Revoked = 'REVOKED';
}
