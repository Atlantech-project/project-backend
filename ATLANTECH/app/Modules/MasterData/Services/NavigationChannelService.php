<?php

namespace App\Modules\MasterData\Services;

use App\Modules\MasterData\Models\Berth;
use App\Modules\MasterData\Models\NavigationChannel;
use App\Modules\Shared\Audit\AuditService;
use App\Modules\Shared\Support\VersionToken;
use Illuminate\Support\Arr;
use Illuminate\Validation\ValidationException;

/**
 * Creating and editing navigation channels (P2-F1, FR-MD-02). A channel is never deleted; `is_active` turns it off or on.
 * An active channel keeps the same `depth_datum` as every berth (Coding Notes §3), the other direction of the check
 * `BerthService` makes.
 */
class NavigationChannelService
{
    private const array ATTRIBUTES = ['name', 'depth', 'depth_datum', 'width', 'speed_limit_knots', 'is_active'];

    public function __construct(private readonly AuditService $audit) {}

    /**
     * @param  array{name: string, depth: numeric, depth_datum: string, width: numeric, speed_limit_knots: numeric, is_active?: bool}  $data
     *
     * @throws ValidationException 422 `errors.navigation_channels.datum_mismatch`.
     */
    public function create(array $data, int $actorId): NavigationChannel
    {
        return $this->audit->transaction(function () use ($data, $actorId): NavigationChannel {
            $channel = new NavigationChannel(['is_active' => true, ...Arr::only($data, self::ATTRIBUTES)]);
            $this->assertDatumMatchesBerths($channel);

            $channel->save();
            $channel->refresh();
            $this->audit->recordCreated($channel, $actorId);

            return $channel;
        });
    }

    /**
     * @param  array{name?: string, depth?: numeric, depth_datum?: string, width?: numeric, speed_limit_knots?: numeric, is_active?: bool}  $data
     *
     * @throws ValidationException 422 `errors.navigation_channels.datum_mismatch`.
     */
    public function update(NavigationChannel $channel, array $data, string $version, int $actorId): NavigationChannel
    {
        return $this->audit->transaction(function () use ($channel, $data, $version, $actorId): NavigationChannel {
            $locked = VersionToken::lockAndAssert($channel, $version);

            $locked->fill(Arr::only($data, self::ATTRIBUTES));

            if ($locked->isDirty(['depth_datum', 'is_active'])) {
                $this->assertDatumMatchesBerths($locked);
            }

            $locked->save();
            $this->audit->recordUpdated($locked, $actorId);

            return $locked->refresh();
        });
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
