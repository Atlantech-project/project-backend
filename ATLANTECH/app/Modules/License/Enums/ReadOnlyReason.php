<?php

namespace App\Modules\License\Enums;

/**
 * `license_states.read_only_reason`.
 */
enum ReadOnlyReason: string
{
    case None = 'NONE';
    case NoLicense = 'NO_LICENSE';
    case TokenExpired = 'TOKEN_EXPIRED';
    case Suspended = 'SUSPENDED';
    case Revoked = 'REVOKED';
    case InvalidSignature = 'INVALID_SIGNATURE';
    case ClockRollback = 'CLOCK_ROLLBACK';
    case PortSuspended = 'PORT_SUSPENDED';
    case PortOffboarded = 'PORT_OFFBOARDED';
    case LicenseNotYetInEffect = 'LICENSE_NOT_YET_IN_EFFECT';
}
