<?php

namespace App\Modules\Yard\Enums;

/**
 * `yard_runs.outcome`.
 */
enum YardRunOutcome: string
{
    case YardRecGenerated = 'YARD_REC_GENERATED';
    case GenerationFailed = 'GENERATION_FAILED';
}
