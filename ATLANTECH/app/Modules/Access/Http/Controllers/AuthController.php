<?php

namespace App\Modules\Access\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Access\Http\Requests\ChangePasswordRequest;
use App\Modules\Access\Http\Requests\LoginRequest;
use App\Modules\Access\Http\Resources\UserProfileResource;
use App\Modules\Access\Services\PasswordChangeService;
use App\Modules\Access\Services\SignInService;
use App\Modules\Shared\Errors\ApiProblemException;
use App\Modules\Shared\Errors\ErrorCode;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;

/**
 * Web sign-in with the Sanctum SPA session (CT-03, sequence 2.1).
 */
class AuthController extends Controller
{
    public function __construct(private readonly SignInService $signIn) {}

    /**
     * POST /api/v1/auth/login: 200 profile with a new session id; 400, 422, 401, 403 ACCOUNT_DISABLED, 419, 429.
     *
     * @throws ApiProblemException 400 BAD_REQUEST when the request does not come from a stateful web-app origin
     *                             (no session to start); checked before the credentials, so not throttled or audited.
     */
    public function login(LoginRequest $request): UserProfileResource
    {
        if (! $request->hasSession()) {
            throw new ApiProblemException(ErrorCode::BadRequest);
        }

        $user = $this->signIn->authenticate(
            $request->string('username')->value(),
            $request->string('password')->value(),
            (string) $request->ip(),
            $request->method().' /'.$request->path(),
        );

        Auth::guard('web')->login($user);
        $request->session()->regenerate();

        return new UserProfileResource($user->load('activeRoleAssignment.organizationUnit'));
    }

    /**
     * GET /api/v1/auth/me: the signed-in user's profile, the same shape as the login answer; 401 without a session,
     * 403 ACCOUNT_DISABLED for a deactivated user.
     */
    public function me(Request $request): UserProfileResource
    {
        return new UserProfileResource($request->user()->load('activeRoleAssignment.organizationUnit'));
    }

    /**
     * POST /api/v1/auth/change-password: saves the new password, clears the flag and answers with the profile and a
     * new session id (P1-C2); 401, 403 ACCOUNT_DISABLED, 419, 422, 429.
     */
    public function changePassword(ChangePasswordRequest $request, PasswordChangeService $passwords): UserProfileResource
    {
        $user = $request->user();

        $passwords->change($user, $request->string('new_password')->value());

        if ($request->hasSession()) {
            $request->session()->regenerate();
        }

        return new UserProfileResource($user->load('activeRoleAssignment.organizationUnit'));
    }

    /**
     * POST /api/v1/auth/logout: ends the web session; 204, 401, 403 ACCOUNT_DISABLED, 419. The mobile token is P1-C4.
     */
    public function logout(Request $request): Response
    {
        Auth::guard('web')->logout();

        if ($request->hasSession()) {
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }

        return response()->noContent();
    }
}
