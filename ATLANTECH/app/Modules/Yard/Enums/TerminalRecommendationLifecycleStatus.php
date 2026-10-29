<?php

namespace App\Modules\Yard\Enums;

/**
 * `terminal_recommendations.lifecycle_status`.
 */
enum TerminalRecommendationLifecycleStatus: string
{
    case YardRecRequested = 'YARD_REC_REQUESTED';
    case YardRecGenerated = 'YARD_REC_GENERATED';
    case GenerationFailed = 'GENERATION_FAILED';
    case YardRecAccepted = 'YARD_REC_ACCEPTED';
    case YardRecRejected = 'YARD_REC_REJECTED';
    case ExportedToTos = 'EXPORTED_TO_TOS';
}
