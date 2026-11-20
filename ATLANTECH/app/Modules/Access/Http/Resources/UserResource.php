<?php

namespace App\Modules\Access\Http\Resources;

use App\Modules\Access\Models\User;
use App\Modules\Shared\Support\VersionToken;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A user in the admin lists (P-23). Never holds the password hash.
 *
 * @mixin User
 */
class UserResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'username' => $this->username,
            'phone' => $this->phone,
            'is_active' => $this->is_active,
            'must_change_password' => $this->must_change_password,
            'version' => VersionToken::of($this->resource),
            'created_at' => $this->created_at,
            'active_role' => $this->activeRoleAssignment === null ? null : new UserRoleAssignmentResource($this->activeRoleAssignment),
        ];
    }
}
