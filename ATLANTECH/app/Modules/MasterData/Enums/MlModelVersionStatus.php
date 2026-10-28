<?php

namespace App\Modules\MasterData\Enums;

/**
 * `ml_model_versions.status`.
 */
enum MlModelVersionStatus: string
{
    case Challenger = 'CHALLENGER';
    case Champion = 'CHAMPION';
    case Archived = 'ARCHIVED';
    case Rejected = 'REJECTED';
}
