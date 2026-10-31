<?php

namespace App\Modules\Access\Http\Middleware;

use App\Modules\Access\Models\User;
use App\Modules\Access\Services\SignInService;
use App\Modules\Shared\Errors\ApiProblemException;
use App\Modules\Shared\Errors\ErrorCode;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Runs right after authentication on every signed-in route (P1-C2, Coding Notes §6.1).
 *
 * A deactivated user is refused and signed out on the next request (FR-AC-10). A user with `must_change_password`
 * can only read their profile, change the password and log out until it is changed (FR-AC-03).
 */
class EnsureAccountUsable
{
    public function __construct(private readonly SignInService $signIn) {}

    /**
     * Route names open while the password change is pending.
     *
     * @var list<string>
     */
    private const array ALLOWED_DURING_PASSWORD_CHANGE = ['auth.me', 'auth.change-password', 'auth.logout'];

    /**
     * @param  Closure(Request): Response  $next
     *
     * @throws ApiProblemException 403 ACCOUNT_DISABLED or PASSWORD_CHANGE_REQUIRED.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user instanceof User) {
            return $next($request);
        }

        if (! $user->is_active) {
            $this->audit($request, $user, ErrorCode::AccountDisabled, 'account_disabled');
            $this->endSession($request);

            throw new ApiProblemException(ErrorCode::AccountDisabled);
        }

        if ($user->must_change_password && ! $request->routeIs(...self::ALLOWED_DURING_PASSWORD_CHANGE)) {
            $this->audit($request, $user, ErrorCode::PasswordChangeRequired, 'password_change_required');

            throw new ApiProblemException(ErrorCode::PasswordChangeRequired);
        }

        return $next($request);
    }

    private function audit(Request $request, User $user, ErrorCode $code, string $reason): void
    {
        $this->signIn->recordRejectedAttempt($request->method().' /'.$request->path(), $code, $reason, $user->username, $user);
    }

    private function endSession(Request $request): void
    {
        Auth::guard('web')->logout();

        if ($request->hasSession()) {
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }
    }
}
