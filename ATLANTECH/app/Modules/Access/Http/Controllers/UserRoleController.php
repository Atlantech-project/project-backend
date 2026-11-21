<?php

namespace App\Modules\Access\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Access\Enums\Role;
use App\Modules\Access\Http\Requests\AssignRoleRequest;
use App\Modules\Access\Http\Requests\UserVersionRequest;
use App\Modules\Access\Http\Resources\UserDetailResource;
use App\Modules\Access\Models\User;
use App\Modules\Access\Services\RoleAssignmentService;
use Illuminate\Http\Request;

/**
 * Give a user a role or end it (P2-E2, FR-AC-11, FR-AC-12); the routes carry `ability:users.manage` and `version.token:user`.
 */
class UserRoleController extends Controller
{
    public function __construct(private readonly RoleAssignmentService $roles) {}

    /**
     * POST /api/v1/users/{user}/roles: 200 with the history; 409 VERSION_CONFLICT or CONFLICT (last Port Admin); 422.
     */
    public function assign(AssignRoleRequest $request, User $user): UserDetailResource
    {
        $data = $request->validated();

        $changed = $this->roles->assign(
            $user,
            Role::from($data['role']),
            isset($data['terminal_id']) ? (int) $data['terminal_id'] : null,
            $data['version'],
            $request->user(),
            $this->route($request),
        );

        return UserDetailResource::for($changed);
    }

    /**
     * POST /api/v1/users/{user}/roles/end: 200; the user is left without a role.
     */
    public function end(UserVersionRequest $request, User $user): UserDetailResource
    {
        return UserDetailResource::for($this->roles->end($user, $request->validated('version'), $request->user(), $this->route($request)));
    }

    private function route(Request $request): string
    {
        return $request->method().' /'.$request->path();
    }
}
