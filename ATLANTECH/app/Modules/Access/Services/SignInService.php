<?php

namespace App\Modules\Access\Services;

use App\Modules\Access\Models\User;
use App\Modules\Shared\Enums\AuditLogAction;
use App\Modules\Shared\Errors\ApiProblemException;
use App\Modules\Shared\Errors\ErrorCode;
use App\Modules\Shared\Models\AuditLog;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;

/**
 * Checks a sign-in attempt: throttle, credentials, active account (FR-AC-01, P1-C1). Shared by the web
 * sign-in and, later, the mobile sign-in (P1-C3); starting the session or issuing a token is the caller's job.
 *
 * Throttle (lead, 2026-10-29): 5 failed attempts per minute per username + IP, and 20 per minute per IP.
 * Refusals 401 and 403 are audited as REJECTED_ATTEMPT (FR-GL-02); 422 and 429 are not.
 */
class SignInService
{
    public const int MAX_ATTEMPTS_PER_USERNAME = 5;

    public const int MAX_ATTEMPTS_PER_IP = 20;

    public const int DECAY_SECONDS = 60;

    /**
     * The active user the credentials belong to.
     *
     * @param  string  $username  Already trimmed and lowercased (LoginRequest).
     * @param  string  $route  Method and path of the request, for the audit row, e.g. "POST /api/v1/auth/login".
     *
     * @throws ApiProblemException 429 TOO_MANY_ATTEMPTS, 401 UNAUTHENTICATED or 403 ACCOUNT_DISABLED.
     */
    public function authenticate(string $username, string $password, string $ip, string $route): User
    {
        $usernameKey = $this->usernameKey($username, $ip);
        $ipKey = $this->ipKey($ip);

        $this->ensureNotThrottled($usernameKey, $ipKey);

        $provider = Auth::guard('web')->getProvider();
        $user = $provider->retrieveByCredentials(['username' => $username]);

        if (! $user instanceof User) {
            // Hash anyway, so an unknown username takes as long as a wrong password (no user enumeration by timing).
            Hash::check($password, $this->dummyHash());
            $this->refuse($usernameKey, $ipKey, ErrorCode::Unauthenticated, 'invalid_credentials', $username, $route);
        }

        if (! $provider->validateCredentials($user, ['password' => $password])) {
            $this->refuse($usernameKey, $ipKey, ErrorCode::Unauthenticated, 'invalid_credentials', $username, $route);
        }

        if (! $user->is_active) {
            $this->refuse($usernameKey, $ipKey, ErrorCode::AccountDisabled, 'account_disabled', $username, $route);
        }

        RateLimiter::clear($usernameKey);

        return $user;
    }

    /**
     * Audit a refused sign-in (Coding Notes §6.4): no actor and no entity; `after_state` holds the route, the HTTP
     * status and the error code (review 1 decision), the reason and the input username, but never the password.
     * Until the audit service exists (P2-D3) this is the one place that writes such a row.
     */
    public function recordRejectedAttempt(string $route, ErrorCode $code, string $reason, string $username): void
    {
        DB::transaction(fn () => AuditLog::query()->create([
            'actor_id' => null,
            'entity_type' => 'users',
            'entity_id' => null,
            'action' => AuditLogAction::RejectedAttempt,
            'after_state' => [
                'route' => $route,
                'status' => $code->status(),
                'code' => $code->value,
                'reason' => $reason,
                'username' => $username,
            ],
        ]));
    }

    /**
     * @throws ApiProblemException 429 TOO_MANY_ATTEMPTS with Retry-After.
     */
    private function ensureNotThrottled(string $usernameKey, string $ipKey): void
    {
        $blockedKeys = array_filter([
            $usernameKey => RateLimiter::tooManyAttempts($usernameKey, self::MAX_ATTEMPTS_PER_USERNAME),
            $ipKey => RateLimiter::tooManyAttempts($ipKey, self::MAX_ATTEMPTS_PER_IP),
        ]);

        if ($blockedKeys !== []) {
            $retryAfter = max(array_map(fn (string $key) => RateLimiter::availableIn($key), array_keys($blockedKeys)));

            throw new ApiProblemException(ErrorCode::TooManyAttempts, headers: ['Retry-After' => (string) max(1, $retryAfter)]);
        }
    }

    /**
     * Count the failure, audit it and answer with the code.
     *
     * @throws ApiProblemException always.
     */
    private function refuse(string $usernameKey, string $ipKey, ErrorCode $code, string $reason, string $username, string $route): never
    {
        RateLimiter::hit($usernameKey, self::DECAY_SECONDS);
        RateLimiter::hit($ipKey, self::DECAY_SECONDS);

        $this->recordRejectedAttempt($route, $code, $reason, $username);

        throw new ApiProblemException($code);
    }

    /**
     * A hash made with the current hashing settings, checked when the username is unknown.
     */
    private function dummyHash(): string
    {
        static $hash = null;

        return $hash ??= Hash::make(bin2hex(random_bytes(16)));
    }

    private function usernameKey(string $username, string $ip): string
    {
        return 'sign-in:username:'.sha1($username.'|'.$ip);
    }

    private function ipKey(string $ip): string
    {
        return 'sign-in:ip:'.$ip;
    }
}
