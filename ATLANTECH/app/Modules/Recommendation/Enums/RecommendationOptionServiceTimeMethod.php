<?php

namespace App\Modules\Recommendation\Enums;

/**
 * `recommendation_options.service_time_method`.
 */
enum RecommendationOptionServiceTimeMethod: string
{
    case Formula = 'FORMULA';
    case Ml = 'ML';
}
