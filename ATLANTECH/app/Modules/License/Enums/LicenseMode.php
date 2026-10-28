<?php

namespace App\Modules\License\Enums;

/**
 * `license_states.mode`.
 */
enum LicenseMode: string
{
    case Setup = 'SETUP';
    case Full = 'FULL';
    case ReadOnly = 'READ_ONLY';
}
