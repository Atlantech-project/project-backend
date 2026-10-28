<?php

namespace App\Modules\Recommendation\Enums;

/**
 * `recommendations.source`.
 */
enum RecommendationSource: string
{
    case Ai = 'AI';
    case Fifo = 'FIFO';
}
