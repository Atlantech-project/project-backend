<?php

namespace App\Modules\Access\Http\Resources;

use App\Modules\Access\Models\User;
use Illuminate\Http\Request;

/**
 * A user with the full role history, newest first (P-24, DEC-36).
 *
 * @mixin User
 */
class UserDetailResource extends UserResource
{
    /**
     * The resource with the active role and the history loaded (history newest first).
     */
    public static function for(User $user): self
    {
        $user->unsetRelations()->load([
            'activeRoleAssignment.organizationUnit',
            'roleAssignments' => fn ($assignments) => $assignments->with('organizationUnit')->orderByDesc('active_from')->orderByDesc('id'),
        ]);

        return new self($user);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            ...parent::toArray($request),
            'role_history' => UserRoleAssignmentResource::collection($this->roleAssignments),
        ];
    }
}
