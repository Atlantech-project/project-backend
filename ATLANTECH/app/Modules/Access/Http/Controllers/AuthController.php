<?php

namespace App\Modules\Access\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Access\Http\Requests\LoginRequest;
use App\Modules\Access\Http\Resources\UserProfileResource;
use App\Modules\Access\Services\SignInService;
use Illuminate\Support\Facades\Auth;

/**
 * Web sign-in with the Sanctum SPA session (CT-03, sequence 2.1).
 */
class AuthController extends Controller
{
    public function __construct(private readonly SignInService $signIn) {}

    /**
     * POST /api/v1/auth/login: 200 profile with a new session id; 422, 401, 403 ACCOUNT_DISABLED, 419, 429.
     */
    public function login(LoginRequest $request): UserProfileResource
    {
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
}
