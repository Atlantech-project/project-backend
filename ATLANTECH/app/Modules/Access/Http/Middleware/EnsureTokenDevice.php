<?php

namespace App\Modules\Access\Http\Middleware;

use App\Modules\Access\Models\PersonalAccessToken;
use App\Modules\Access\Models\User;
use App\Modules\Shared\Audit\AuditService;
use App\Modules\Shared\Errors\ApiProblemException;
use App\Modules\Shared\Errors\ErrorCode;
use App\Modules\Shared\Support\AppVersion;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

/**
 * Right after authentication, Bearer requests only (P1-C3): the token belongs to one device, so `X-Device-Id` must name it.
 * Missing: 422; another device or a device the Port Admin revoked: 401, audited (lead's default D6, 2026-11-01; review 3). A Bearer
 * request must also send a well-formed `X-App-Version` (422; lead's decision D-B, 2026-11-05); the 426 comparison is `EnsureMinAppVersion`.
 * Only a matching request counts as a use: it stamps the token and slides its expiry (Sanctum's own stamp is off in
 * `config/sanctum.php`). Web session requests pass, with or without the version header.
 *
 * Every Bearer route runs this step through the `port.api` group.
 */
class EnsureTokenDevice
{
    public function __construct(private readonly AuditService $audit) {}

    /**
     * @param  Closure(Request): Response  $next
     *
     * @throws ValidationException 422 VALIDATION_FAILED without `X-Device-Id`, or without a well-formed `X-App-Version`.
     * @throws ApiProblemException 401 UNAUTHENTICATED for another device or a revoked one.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->user()?->currentAccessToken();

        if (! $token instanceof PersonalAccessToken) {
            return $next($request);
        }

        $deviceId = $request->header('X-Device-Id');

        EnsureMobileHeaders::validateHeaders(
            ['X-Device-Id' => $deviceId, 'X-App-Version' => $request->header('X-App-Version')],
            ['X-Device-Id' => EnsureMobileHeaders::deviceIdRules(), 'X-App-Version' => AppVersion::rules()],
        );

        if ($token->device?->device_id !== $deviceId) {
            $this->refuse($request, 'device_mismatch');
        }

        if ($token->device->revoked_at !== null) {
            $token->revoke();
            $this->refuse($request, 'device_revoked');
        }

        $token->markUsed();

        return $next($request);
    }

    /**
     * @throws ApiProblemException 401 UNAUTHENTICATED, after the audit row.
     */
    private function refuse(Request $request, string $reason): never
    {
        /** @var User $user */
        $user = $request->user();
        $this->audit->rejectedAttempt(
            $request->method().' /'.$request->path(),
            ErrorCode::Unauthenticated,
            $reason,
            $user->id,
            $user->username,
            'users',
            $user->id,
        );

        throw new ApiProblemException(ErrorCode::Unauthenticated);
    }
}
