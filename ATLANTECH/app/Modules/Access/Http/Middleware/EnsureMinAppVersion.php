<?php

namespace App\Modules\Access\Http\Middleware;

use App\Modules\Shared\Errors\ApiProblemException;
use App\Modules\Shared\Support\AppVersion;
use App\Modules\Shared\Support\MinAppVersionSource;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

/**
 * Write chain step "minimum app version" (FR-GL-01, alias `app.version`, group `port.api`): after the role and terminal
 * checks, before the licence step. A mobile request below the port's `min_app_version` answers 426 APP_UPDATE_REQUIRED.
 *
 * Applies to any request that carries `X-App-Version` (P2-D2, lead's answer Q2); the web app sends none. Hook from P2-D2,
 * body from P1-C3. Its position in the order is fixed by the priority list in `bootstrap/app.php`; do not move it.
 */
class EnsureMinAppVersion
{
    public function __construct(private readonly MinAppVersionSource $versions) {}

    /**
     * @param  Closure(Request): Response  $next
     *
     * @throws ValidationException 422 VALIDATION_FAILED for a malformed version.
     * @throws ApiProblemException 426 APP_UPDATE_REQUIRED, or 503 while the port has no `min_app_version`.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $version = $request->header('X-App-Version');

        if ($version === null) {
            return $next($request);
        }

        EnsureMobileHeaders::validateHeaders(['X-App-Version' => $version], ['X-App-Version' => AppVersion::rules()]);

        $minimum = $this->versions->minAppVersion();

        if (AppVersion::isOlderThan($version, $minimum)) {
            throw ApiProblemException::appUpdateRequired($minimum);
        }

        return $next($request);
    }
}
