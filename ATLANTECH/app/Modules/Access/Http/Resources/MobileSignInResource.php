<?php

namespace App\Modules\Access\Http\Resources;

use App\Modules\Access\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Laravel\Sanctum\NewAccessToken;

/**
 * The mobile sign-in answer (CT-04): the token, its expiry at sign-in (it then slides with each use) and the same profile as the web.
 *
 * @property NewAccessToken $resource
 */
class MobileSignInResource extends JsonResource
{
    /**
     * @return array{token: string, expires_at: mixed, user: UserProfileResource}
     */
    public function toArray(Request $request): array
    {
        /** @var User $user */
        $user = $this->resource->accessToken->tokenable;

        return [
            'token' => $this->resource->plainTextToken,
            'expires_at' => $this->resource->accessToken->expires_at,
            'user' => new UserProfileResource($user->load('activeRoleAssignment.organizationUnit')),
        ];
    }
}
