<?php

namespace App\Modules\Recommendation\Enums;

/**
 * `recommendation_overrides.component_type`.
 */
enum RecommendationOverrideComponentType: string
{
    case Berth = 'BERTH';
    case Pilot = 'PILOT';
    case Tug = 'TUG';
    case StartTime = 'START_TIME';
}
