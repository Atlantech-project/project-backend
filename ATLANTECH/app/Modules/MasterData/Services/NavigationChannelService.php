<?php

namespace App\Modules\MasterData\Services;

use App\Modules\MasterData\Models\Berth;
use App\Modules\MasterData\Models\ChannelWaypoint;
use App\Modules\MasterData\Models\NavigationChannel;
use App\Modules\Shared\Audit\AuditService;
use App\Modules\Shared\Enums\AuditLogAction;
use App\Modules\Shared\Support\VersionToken;
use Illuminate\Support\Arr;
use Illuminate\Validation\ValidationException;

/**
 * Creating, editing, deactivating and reactivating navigation channels (P2-F1, FR-MD-02). A channel is never deleted.
 * An active channel keeps the same `depth_datum` as every berth (Coding Notes §3), the other direction of the check
 * `BerthService` makes.
 */
class NavigationChannelService
{
    private const array ATTRIBUTES = ['name', 'depth', 'depth_datum', 'width', 'speed_limit_knots'];

    public function __construct(private readonly AuditService $audit) {}

    /**
     * A new channel is active.
     *
     * @param  array{name: string, depth: numeric, depth_datum: string, width: numeric, speed_limit_knots: numeric}  $data
     *
     * @throws ValidationException 422 `errors.navigation_channels.datum_mismatch`.
     */
    public function create(array $data, int $actorId): NavigationChannel
    {
        return $this->audit->transaction(function () use ($data, $actorId): NavigationChannel {
            $channel = new NavigationChannel([...Arr::only($data, self::ATTRIBUTES), 'is_active' => true]);
            $this->assertDatumMatchesBerths($channel);

            $channel->save();
            $channel->refresh();
            $this->audit->recordCreated($channel, $actorId);

            return $channel;
        });
    }

    /**
     * @param  array{name?: string, depth?: numeric, depth_datum?: string, width?: numeric, speed_limit_knots?: numeric}  $data
     *
     * @throws ValidationException 422 `errors.navigation_channels.datum_mismatch`.
     */
    public function update(NavigationChannel $channel, array $data, string $version, int $actorId): NavigationChannel
    {
        return $this->audit->transaction(function () use ($channel, $data, $version, $actorId): NavigationChannel {
            $locked = VersionToken::lockAndAssert($channel, $version);

            $locked->fill(Arr::only($data, self::ATTRIBUTES));

            if ($locked->isDirty('depth_datum')) {
                $this->assertDatumMatchesBerths($locked);
            }

            $locked->save();
            $this->audit->recordUpdated($locked, $actorId);

            return $locked->refresh();
        });
    }

    public function deactivate(NavigationChannel $channel, string $version, int $actorId): NavigationChannel
    {
        return $this->setActive($channel, false, $version, $actorId);
    }

    /**
     * @throws ValidationException 422 `errors.navigation_channels.datum_mismatch`.
     */
    public function activate(NavigationChannel $channel, string $version, int $actorId): NavigationChannel
    {
        return $this->setActive($channel, true, $version, $actorId);
    }

    /**
     * Already in the asked state: a no-op (200, nothing written).
     */
    private function setActive(NavigationChannel $channel, bool $active, string $version, int $actorId): NavigationChannel
    {
        return $this->audit->transaction(function () use ($channel, $active, $version, $actorId): NavigationChannel {
            $locked = VersionToken::lockAndAssert($channel, $version);

            if ($locked->is_active === $active) {
                return $locked;
            }

            $locked->is_active = $active;
            $this->assertDatumMatchesBerths($locked);
            $locked->save();
            $this->audit->recordUpdated($locked, $actorId);

            return $locked->refresh();
        });
    }

    /**
     * Save the whole waypoint list in travel order (P-26: add, reorder, remove). The waypoints are part of the channel:
     * the change moves the channel's version, and its audit row keeps the old list, since removed rows are deleted. The
     * same list again writes nothing.
     *
     * @param  list<array{label: string, latitude: numeric, longitude: numeric}>  $waypoints
     */
    public function replaceWaypoints(NavigationChannel $channel, array $waypoints, string $version, int $actorId): NavigationChannel
    {
        return $this->audit->transaction(function () use ($channel, $waypoints, $version, $actorId): NavigationChannel {
            $locked = VersionToken::lockAndAssert($channel, $version);

            $old = $this->waypointList($locked);
            $new = [];

            foreach ($waypoints as $index => $waypoint) {
                $new[] = [
                    'sequence_order' => $index + 1,
                    'label' => $waypoint['label'],
                    'latitude' => round((float) $waypoint['latitude'], 6),
                    'longitude' => round((float) $waypoint['longitude'], 6),
                ];
            }

            if ($old === $new) {
                return $locked;
            }

            // Delete and insert: updating rows in place would collide on the unique order and label while reordering.
            ChannelWaypoint::query()->where('channel_id', $locked->id)->delete();

            foreach ($new as $waypoint) {
                $locked->channelWaypointsByChannel()->create($waypoint);
            }

            $locked->touch();
            $this->audit->record(AuditLogAction::Update, $locked->getTable(), $locked->id, $actorId, ['waypoints' => $old], ['waypoints' => $new]);

            return $locked->refresh();
        });
    }

    /**
     * @return list<array{sequence_order: int, label: string, latitude: float, longitude: float}>
     */
    private function waypointList(NavigationChannel $channel): array
    {
        return ChannelWaypoint::query()->where('channel_id', $channel->id)->orderBy('sequence_order')->get()
            ->map(fn (ChannelWaypoint $waypoint): array => [
                'sequence_order' => (int) $waypoint->sequence_order,
                'label' => $waypoint->label,
                'latitude' => (float) $waypoint->latitude,
                'longitude' => (float) $waypoint->longitude,
            ])
            ->values()
            ->all();
    }

    /**
     * Every berth counts, whatever its status: berths are never deleted and must share the channel's datum. An inactive
     * channel is not checked, as `BerthService` compares berths with active channels only.
     *
     * @throws ValidationException 422 `errors.navigation_channels.datum_mismatch`.
     */
    private function assertDatumMatchesBerths(NavigationChannel $channel): void
    {
        if ($channel->is_active && Berth::query()->where('depth_datum', '!=', $channel->depth_datum)->exists()) {
            throw ValidationException::withMessages(['depth_datum' => 'errors.navigation_channels.datum_mismatch']);
        }
    }
}
