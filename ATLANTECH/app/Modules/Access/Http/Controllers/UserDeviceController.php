<?php

namespace App\Modules\Access\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Access\Http\Resources\UserDeviceCollection;
use App\Modules\Access\Http\Resources\UserDeviceResource;
use App\Modules\Access\Models\User;
use App\Modules\Access\Models\UserDevice;
use App\Modules\Access\Services\DeviceRevocationService;
use App\Modules\Shared\Http\Pagination\PageRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * A user's mobile devices for the Port Admin (P2-E3, FR-AC-13); routes carry `ability:devices.manage` and `{device}` is scoped to `{user}`.
 */
class UserDeviceController extends Controller
{
    public function __construct(private readonly DeviceRevocationService $devices) {}

    /**
     * GET /api/v1/users/{user}/devices: a page, newest first; 401, 403, 404, 422.
     */
    public function index(Request $request, User $user): UserDeviceCollection
    {
        $query = UserDevice::query()->withLiveTokenCount()->where('user_id', $user->id)->orderByDesc('id');

        return new UserDeviceCollection(PageRequest::fromRequest($request)->paginate($query));
    }

    /**
     * POST /api/v1/users/{user}/devices/{device}/revoke: 200; revoking again changes nothing.
     */
    public function revoke(Request $request, User $user, UserDevice $device): UserDeviceResource
    {
        $this->devices->revoke($device, $request->user()->id);

        return $this->getResourceOf($device);
    }

    /**
     * POST /api/v1/users/{user}/devices/revoke-all: 200 with the counts.
     */
    public function revokeAll(Request $request, User $user): JsonResponse
    {
        return response()->json($this->devices->revokeAll($user, $request->user()->id));
    }

    /**
     * POST /api/v1/users/{user}/devices/{device}/restore: 200; the old tokens stay revoked.
     */
    public function restore(Request $request, User $user, UserDevice $device): UserDeviceResource
    {
        $this->devices->restore($device, $request->user()->id);

        return $this->getResourceOf($device);
    }

    private function getResourceOf(UserDevice $device): UserDeviceResource
    {
        return new UserDeviceResource(UserDevice::query()->withLiveTokenCount()->findOrFail($device->id));
    }
}
