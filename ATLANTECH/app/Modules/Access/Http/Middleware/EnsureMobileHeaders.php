<?php

namespace App\Modules\Access\Http\Middleware;

use App\Modules\Shared\Support\AppVersion;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

/**
 * Mobile sign-in only (CT-04): both device headers are required and checked before the 426 and the body, so an old app learns
 * it must update even when its body is outdated (lead's default D3, 2026-11-01). A missing or malformed header is 422.
 */
class EnsureMobileHeaders
{
    /**
     * `X-Device-Id` as stored in `user_devices.device_id` (100 characters).
     *
     * @return list<string>
     */
    public static function deviceIdRules(): array
    {
        return ['required', 'string', 'max:100'];
    }

    /**
     * Validate header values; the header name is the field in `errors` and in the message ("The X-Device-Id field is required.").
     *
     * @param  array<string, string|null>  $headers  Header name => value.
     * @param  array<string, list<string>>  $rules  Header name => rules.
     *
     * @throws ValidationException 422 VALIDATION_FAILED.
     */
    public static function validateHeaders(array $headers, array $rules): void
    {
        $names = array_keys($headers);

        Validator::make($headers, $rules, [], array_combine($names, $names))->validate();
    }

    /**
     * @param  Closure(Request): Response  $next
     *
     * @throws ValidationException 422 VALIDATION_FAILED.
     */
    public function handle(Request $request, Closure $next): Response
    {
        self::validateHeaders(
            ['X-App-Version' => $request->header('X-App-Version'), 'X-Device-Id' => $request->header('X-Device-Id')],
            ['X-App-Version' => AppVersion::rules(), 'X-Device-Id' => self::deviceIdRules()],
        );

        return $next($request);
    }
}
