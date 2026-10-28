<?php

namespace App\Modules\MasterData\Enums;

/**
 * `constraint_types.comparator`.
 */
enum ConstraintTypeComparator: string
{
    case MaxAllowed = 'MAX_ALLOWED';
    case MinRequired = 'MIN_REQUIRED';
}
