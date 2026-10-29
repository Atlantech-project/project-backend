<?php

namespace App\Modules\License\Enums;

/**
 * `license_states.port_status`.
 */
enum PortStatus: string
{
    case Onboarding = 'ONBOARDING';
    case Active = 'ACTIVE';
    case Suspended = 'SUSPENDED';
    case Offboarded = 'OFFBOARDED';
}
