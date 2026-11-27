<?php

namespace App\Modules\MasterData\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\MasterData\Http\Requests\NavigationChannelIndexRequest;
use App\Modules\MasterData\Http\Requests\NavigationChannelStatusRequest;
use App\Modules\MasterData\Http\Requests\ReplaceChannelWaypointsRequest;
use App\Modules\MasterData\Http\Requests\StoreNavigationChannelRequest;
use App\Modules\MasterData\Http\Requests\UpdateNavigationChannelRequest;
use App\Modules\MasterData\Http\Resources\NavigationChannelCollection;
use App\Modules\MasterData\Http\Resources\NavigationChannelResource;
use App\Modules\MasterData\Models\NavigationChannel;
use App\Modules\MasterData\Services\NavigationChannelService;
use Illuminate\Http\JsonResponse;

/**
 * Navigation channels (P2-F1, FR-MD-02, P-26). Reads carry `ability:port-state.read`, writes `ability:port-setup.manage`.
 * There is no delete: a channel is deactivated and activated by its own endpoints.
 */
class NavigationChannelController extends Controller
{
    public function __construct(private readonly NavigationChannelService $channels) {}

    /**
     * GET /api/v1/navigation-channels: a page ordered by name, with waypoints; filter `is_active`; 401, 403, 422.
     */
    public function index(NavigationChannelIndexRequest $request): NavigationChannelCollection
    {
        $query = NavigationChannel::query()
            ->with('channelWaypointsByChannel')
            ->when($request->has('is_active'), fn ($query) => $query->where('is_active', $request->boolean('is_active')))
            ->orderBy('name')
            ->orderBy('id');

        return new NavigationChannelCollection($request->page()->paginate($query));
    }

    /**
     * POST /api/v1/navigation-channels: 201; 401, 403, 422.
     */
    public function store(StoreNavigationChannelRequest $request): JsonResponse
    {
        $channel = $this->channels->create($request->validated(), $request->user()->id);

        return (new NavigationChannelResource($channel->load('channelWaypointsByChannel')))->response()->setStatusCode(201);
    }

    /**
     * PUT /api/v1/navigation-channels/{channel}: 404, 409 VERSION_CONFLICT, 422.
     */
    public function update(UpdateNavigationChannelRequest $request, NavigationChannel $channel): NavigationChannelResource
    {
        $data = $request->safe()->except('version');
        $updated = $this->channels->update($channel, $data, $request->validated('version'), $request->user()->id);

        return new NavigationChannelResource($updated->load('channelWaypointsByChannel'));
    }

    /**
     * POST /api/v1/navigation-channels/{channel}/deactivate: 200 (also when already inactive); 409 VERSION_CONFLICT.
     */
    public function deactivate(NavigationChannelStatusRequest $request, NavigationChannel $channel): NavigationChannelResource
    {
        $updated = $this->channels->deactivate($channel, $request->validated('version'), $request->user()->id);

        return new NavigationChannelResource($updated->load('channelWaypointsByChannel'));
    }

    /**
     * POST /api/v1/navigation-channels/{channel}/activate: 200 (also when already active); 409 VERSION_CONFLICT; 422 datum.
     */
    public function activate(NavigationChannelStatusRequest $request, NavigationChannel $channel): NavigationChannelResource
    {
        $updated = $this->channels->activate($channel, $request->validated('version'), $request->user()->id);

        return new NavigationChannelResource($updated->load('channelWaypointsByChannel'));
    }

    /**
     * PUT /api/v1/navigation-channels/{channel}/waypoints: the whole list; 404, 409 VERSION_CONFLICT, 422.
     */
    public function replaceWaypoints(ReplaceChannelWaypointsRequest $request, NavigationChannel $channel): NavigationChannelResource
    {
        $updated = $this->channels->replaceWaypoints($channel, $request->validated('waypoints'), $request->validated('version'), $request->user()->id);

        return new NavigationChannelResource($updated->load('channelWaypointsByChannel'));
    }
}
