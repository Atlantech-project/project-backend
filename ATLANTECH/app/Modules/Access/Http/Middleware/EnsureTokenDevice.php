<?php

namespace App\Modules\Access\Http\Middleware;

use App\Modules\Access\Models\PersonalAccessToken;
use App\Modules\Shared\Errors\ApiProblemException;
use App\Modules\Shared\Errors\ErrorCode;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

/**
 * Right after authentication, Bearer requests only (P1-C3): the token belongs to one device, so `X-Device-Id` must name it.
 * Missing: 422; another device: 401 (lead's default D6, 2026-11-01). Web session requests pass.
 */
class EnsureTokenDevice
{
    /**
     * @param  Closure(Request): Response  $next
     *
     * @throws ValidationException 422 VALIDATION_FAILED without the header.
     * @throws ApiProblemException 401 UNAUTHENTICATED for another device.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->user()?->currentAccessToken();

        if (! $token instanceof PersonalAccessToken) {
            return $next($request);
        }

        $deviceId = $request->header('X-Device-Id');

        EnsureMobileHeaders::validateHeaders(['X-Device-Id' => $deviceId], ['X-Device-Id' => EnsureMobileHeaders::deviceIdRules()]);

        if ($token->device?->device_id !== $deviceId) {
            throw new ApiProblemException(ErrorCode::Unauthenticated);
        }

        return $next($request);
    }
}
