<?php

namespace App\Modules\Access\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Access\Enums\DevicePlatform;
use App\Modules\Access\Http\Requests\MobileLoginRequest;
use App\Modules\Access\Http\Resources\MobileSignInResource;
use App\Modules\Access\Services\MobileSignInService;
use App\Modules\Access\Services\SignInService;
use App\Modules\Shared\Errors\ApiProblemException;

/**
 * Mobile sign-in with a device token (CT-04, P1-C3). The route checks the headers (422) and the app version (426) first.
 */
class MobileAuthController extends Controller
{
    public function __construct(private readonly SignInService $signIn, private readonly MobileSignInService $mobile) {}

    /**
     * POST /api/v1/auth/mobile/login: 200 `{token, expires_at, user}`; 422, 426, 429, 401, 403 ACCOUNT_DISABLED, 403 DEVICE_REVOKED.
     *
     * @throws ApiProblemException
     */
    public function login(MobileLoginRequest $request): MobileSignInResource
    {
        $route = $request->method().' /'.$request->path();

        $user = $this->signIn->authenticate(
            $request->string('username')->value(),
            $request->string('password')->value(),
            (string) $request->ip(),
            $route,
        );

        $token = $this->mobile->issueToken(
            $user,
            (string) $request->header('X-Device-Id'),
            $request->enum('platform', DevicePlatform::class),
            (string) $request->header('X-App-Version'),
            $route,
        );

        return new MobileSignInResource($token);
    }
}
