<?php

namespace App\Modules\Recommendation\Enums;

/**
 * `recommendations.purpose`.
 */
enum RecommendationPurpose: string
{
    case Initial = 'INITIAL';
    case DelayReplan = 'DELAY_REPLAN';
}
