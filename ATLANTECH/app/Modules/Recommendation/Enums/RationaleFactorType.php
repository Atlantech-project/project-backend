<?php

namespace App\Modules\Recommendation\Enums;

/**
 * `recommendation_rationale_factors.factor_type`.
 */
enum RationaleFactorType: string
{
    case Constraint = 'CONSTRAINT';
    case ObjectiveTerm = 'OBJECTIVE_TERM';
}
