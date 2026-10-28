<?php

namespace App\Modules\Baplie\Enums;

/**
 * `baplie_parse_runs.error_category`.
 */
enum BaplieParseRunErrorCategory: string
{
    case InvalidEnvelope = 'INVALID_ENVELOPE';
    case PreconditionFailed = 'PRECONDITION_FAILED';
    case DataUnavailable = 'DATA_UNAVAILABLE';
    case ConfigurationError = 'CONFIGURATION_ERROR';
    case Timeout = 'TIMEOUT';
    case InternalError = 'INTERNAL_ERROR';
}
