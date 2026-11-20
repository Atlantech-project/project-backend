<?php

namespace App\Modules\Access\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Access\Http\Requests\StoreUserRequest;
use App\Modules\Access\Http\Requests\UserIndexRequest;
use App\Modules\Access\Http\Requests\UserVersionRequest;
use App\Modules\Access\Http\Resources\UserCollection;
use App\Modules\Access\Http\Resources\UserDetailResource;
use App\Modules\Access\Http\Resources\UserResource;
use App\Modules\Access\Models\User;
use App\Modules\Access\Services\UserAdministrationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * User administration for the Port Admin (P2-E1, FR-AC-09, FR-AC-10); every route carries `ability:users.manage`.
 */
class UserController extends Controller
{
    public function __construct(private readonly UserAdministrationService $users) {}

    /**
     * GET /api/v1/users: a page ordered by name; filters `is_active` and `q`; 401, 403, 422.
     */
    public function index(UserIndexRequest $request): UserCollection
    {
        $filters = $request->validated();

        $query = User::query()
            ->with('activeRoleAssignment.organizationUnit')
            ->when(isset($filters['is_active']), fn ($query) => $query->where('is_active', $request->boolean('is_active')))
            ->when(isset($filters['q']), function ($query) use ($filters) {
                $like = '%'.addcslashes($filters['q'], '\%_').'%';

                return $query->where(fn ($match) => $match->where('name', 'like', $like)->orWhere('username', 'like', $like));
            })
            ->orderBy('name')
            ->orderBy('id');

        return new UserCollection($request->page()->paginate($query));
    }

    /**
     * GET /api/v1/users/{user}: the user with the role history; 401, 403, 404.
     */
    public function show(User $user): UserDetailResource
    {
        return UserDetailResource::for($user);
    }

    /**
     * POST /api/v1/users: 201 with the one-time password, shown here only; 401, 403, 422 VALIDATION_FAILED or PLAN_LIMIT_EXCEEDED.
     */
    public function store(StoreUserRequest $request): JsonResponse
    {
        $created = $this->users->create($request->validated(), $request->user()->id);

        return response()
            ->json([...(new UserResource($created['user']))->resolve(), 'one_time_password' => $created['one_time_password']], 201)
            ->header('Cache-Control', 'no-store');
    }

    /**
     * POST /api/v1/users/{user}/deactivate: 200; 409 VERSION_CONFLICT or CONFLICT (self, last Port Admin).
     */
    public function deactivate(UserVersionRequest $request, User $user): UserResource
    {
        return new UserResource($this->users->deactivate($user, $request->validated('version'), $request->user(), $this->route($request)));
    }

    /**
     * POST /api/v1/users/{user}/activate: 200; 409 VERSION_CONFLICT; 422 PLAN_LIMIT_EXCEEDED.
     */
    public function activate(UserVersionRequest $request, User $user): UserResource
    {
        return new UserResource($this->users->activate($user, $request->validated('version'), $request->user()));
    }

    private function route(Request $request): string
    {
        return $request->method().' /'.$request->path();
    }
}
