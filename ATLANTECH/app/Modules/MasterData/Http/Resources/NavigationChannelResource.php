<?php

namespace App\Modules\MasterData\Http\Resources;

use App\Modules\MasterData\Models\ChannelWaypoint;
use App\Modules\MasterData\Models\NavigationChannel;
use App\Modules\Shared\Support\VersionToken;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A navigation channel (P2-F1, P-26) with its waypoints in order, when loaded.
 *
 * @mixin NavigationChannel
 */
class NavigationChannelResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'depth' => (float) $this->depth,
            'depth_datum' => $this->depth_datum,
            'width' => (float) $this->width,
            'speed_limit_knots' => (float) $this->speed_limit_knots,
            'is_active' => $this->is_active,
            'version' => VersionToken::of($this->resource),
            'waypoints' => $this->whenLoaded('channelWaypointsByChannel', fn () => $this->channelWaypointsByChannel
                ->sortBy('sequence_order')
                ->map(fn (ChannelWaypoint $waypoint): array => [
                    'sequence_order' => $waypoint->sequence_order,
                    'label' => $waypoint->label,
                    'latitude' => (float) $waypoint->latitude,
                    'longitude' => (float) $waypoint->longitude,
                ])->values()),
        ];
    }
}
