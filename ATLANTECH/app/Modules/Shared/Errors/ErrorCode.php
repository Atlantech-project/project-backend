<?php

namespace App\Modules\Shared\Errors;

use Illuminate\Support\Str;

/**
 * Catalogue of the stable error codes the port API returns in RFC 9457 problem bodies (CT-01, contracts/error-codes.md).
 *
 * A code, its HTTP status and its message key never change once CT-01 is approved. A new code is a new case,
 * a translation in lang/en/errors.php and a row in contracts/error-codes.md, in the same pull request.
 */
enum ErrorCode: string
{
    case ValidationFailed = 'VALIDATION_FAILED';
    case VersionConflict = 'VERSION_CONFLICT';
    case Unauthenticated = 'UNAUTHENTICATED';
    case Forbidden = 'FORBIDDEN';
    case NotFound = 'NOT_FOUND';
    case MethodNotAllowed = 'METHOD_NOT_ALLOWED';
    case AccountDisabled = 'ACCOUNT_DISABLED';
    case PasswordChangeRequired = 'PASSWORD_CHANGE_REQUIRED';
    case AppUpdateRequired = 'APP_UPDATE_REQUIRED';
    /** Reserved: the licence guard is future work (DEC-54). */
    case LicenseReadOnly = 'LICENSE_READ_ONLY';
    /** Reserved: the plan-limit service is future work (DEC-54). */
    case PlanLimitExceeded = 'PLAN_LIMIT_EXCEEDED';
    case TooManyAttempts = 'TOO_MANY_ATTEMPTS';
    case IdempotencyKeyReused = 'IDEMPOTENCY_KEY_REUSED';
    case ServiceUnavailable = 'SERVICE_UNAVAILABLE';
    case ServerError = 'SERVER_ERROR';
    /** Any other client error the framework raises (e.g. 413 payload too large); proposed in P1-A2 step 4. */
    case BadRequest = 'BAD_REQUEST';

    public const string TYPE_BASE_URI = 'https://atlantech.io/errors/';

    /**
     * The HTTP status every response with this code carries.
     */
    public function status(): int
    {
        return match ($this) {
            self::BadRequest => 400,
            self::Unauthenticated => 401,
            self::Forbidden, self::AccountDisabled, self::PasswordChangeRequired => 403,
            self::NotFound => 404,
            self::MethodNotAllowed => 405,
            self::VersionConflict => 409,
            self::ValidationFailed, self::PlanLimitExceeded, self::IdempotencyKeyReused => 422,
            self::LicenseReadOnly => 423,
            self::AppUpdateRequired => 426,
            self::TooManyAttempts => 429,
            self::ServerError => 500,
            self::ServiceUnavailable => 503,
        };
    }

    /**
     * Translation key of the human message, e.g. "errors.version_conflict" (CT-01 examples).
     */
    public function messageKey(): string
    {
        return 'errors.'.strtolower($this->value);
    }

    /**
     * RFC 9457 "type": an identifier for the problem, not a resolvable page.
     */
    public function type(): string
    {
        return self::TYPE_BASE_URI.Str::of($this->value)->lower()->replace('_', '-');
    }

    /**
     * RFC 9457 "title": a short English summary that does not change from occurrence to occurrence.
     */
    public function title(): string
    {
        return match ($this) {
            self::ValidationFailed => 'Validation failed',
            self::VersionConflict => 'Record changed',
            self::Unauthenticated => 'Unauthenticated',
            self::Forbidden => 'Forbidden',
            self::NotFound => 'Not found',
            self::MethodNotAllowed => 'Method not allowed',
            self::AccountDisabled => 'Account disabled',
            self::PasswordChangeRequired => 'Password change required',
            self::AppUpdateRequired => 'App update required',
            self::LicenseReadOnly => 'License is read-only',
            self::PlanLimitExceeded => 'Plan limit exceeded',
            self::TooManyAttempts => 'Too many attempts',
            self::IdempotencyKeyReused => 'Idempotency key reused',
            self::ServiceUnavailable => 'Service unavailable',
            self::ServerError => 'Server error',
            self::BadRequest => 'Bad request',
        };
    }
}
