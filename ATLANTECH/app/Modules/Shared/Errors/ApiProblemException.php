<?php

namespace App\Modules\Shared\Errors;

use InvalidArgumentException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Throwable;

/**
 * A refusal with a stable code from the catalogue, rendered as an RFC 9457 problem body (CT-01).
 *
 * Domain code throws it (sign-in, write chain, version check, licence guard); the API exception renderer
 * turns it into the response. As an HTTP exception it is not reported to the log.
 */
class ApiProblemException extends HttpException
{
    /**
     * Members of the problem body the renderer owns; extension members may not replace them.
     *
     * @var list<string>
     */
    public const array RESERVED_MEMBERS = ['type', 'title', 'status', 'code', 'message_key', 'detail'];

    /**
     * @param  ?string  $detail  English text replacing the catalogue message; null uses the translation of the message key
     * @param  array<string, string|int|float>  $replace  Placeholder values for the translation of the message key
     * @param  array<string, mixed>  $extensions  Extra problem members, e.g. "current" (409) or "min_app_version" (426)
     * @param  array<string, string>  $headers  Extra response headers, e.g. "Retry-After"
     */
    public function __construct(
        public readonly ErrorCode $errorCode,
        public readonly ?string $detail = null,
        public readonly array $replace = [],
        public readonly array $extensions = [],
        array $headers = [],
        ?Throwable $previous = null,
    ) {
        $reserved = array_intersect(array_keys($extensions), self::RESERVED_MEMBERS);

        if ($reserved !== []) {
            throw new InvalidArgumentException('Extension members may not replace problem members: '.implode(', ', $reserved).'.');
        }

        parent::__construct($errorCode->status(), $detail ?? $errorCode->value, $previous, $headers);
    }

    /**
     * 409: the record changed since the client read it; "current" carries the stored record's id and version token.
     *
     * @param  array<string, mixed>  $current  At least "id" and "version" (the updated_at value, ISO 8601 UTC with microseconds)
     */
    public static function versionConflict(array $current, ?string $detail = null): self
    {
        return new self(ErrorCode::VersionConflict, $detail, extensions: ['current' => $current]);
    }

    /**
     * 426: the mobile app is older than the port's minimum version (CT-04).
     */
    public static function appUpdateRequired(string $minAppVersion): self
    {
        return new self(ErrorCode::AppUpdateRequired, extensions: ['min_app_version' => $minAppVersion]);
    }
}
