<?php

namespace App\Modules\Yard\Enums;

/**
 * `yard_runs.error_category`.
 */
enum YardRunErrorCategory: string
{
    case InvalidEnvelope = 'INVALID_ENVELOPE';
    case PreconditionFailed = 'PRECONDITION_FAILED';
    case DataUnavailable = 'DATA_UNAVAILABLE';
    case ConfigurationError = 'CONFIGURATION_ERROR';
    case Infeasible = 'INFEASIBLE';
    case Timeout = 'TIMEOUT';
    case InternalError = 'INTERNAL_ERROR';
}
