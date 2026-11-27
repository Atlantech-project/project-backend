<?php

namespace App\Modules\MasterData\Services;

use App\Modules\MasterData\Enums\TugboatStatus;
use App\Modules\MasterData\Models\Tugboat;
use App\Modules\Shared\Audit\AuditService;
use App\Modules\Shared\Enums\ResourceKind;
use App\Modules\Shared\Events\ResourceChanged;
use App\Modules\Shared\Support\VersionToken;

/**
 * The tug fleet of the Tug Manager (P2-F3, FR-MD-07). Deactivating a tug is setting `INACTIVE`; the row and its tug
 * master link are kept (Q10). A saved change of status, power or capacity dispatches `ResourceChanged` (P2-F7); a new
 * tug or a new name does not.
 */
class TugboatService
{
    private const array PLANNING_ATTRIBUTES = ['status', 'tug_power', 'tug_power_unit', 'capacity'];

    public function __construct(private readonly AuditService $audit) {}

    /**
     * @param  array{name: string, tug_power: string, tug_power_unit: string, capacity?: string|null, status?: string}  $data
     */
    public function create(array $data, int $actorId): Tugboat
    {
        return $this->audit->transaction(function () use ($data, $actorId): Tugboat {
            $tugboat = Tugboat::query()->create([...$data, 'status' => $data['status'] ?? TugboatStatus::Available]);
            $this->audit->recordCreated($tugboat, $actorId);

            return $tugboat->refresh();
        });
    }

    /**
     * @param  array{name?: string, tug_power?: string, tug_power_unit?: string, capacity?: string|null, status?: string}  $data
     */
    public function update(Tugboat $target, array $data, string $version, int $actorId): Tugboat
    {
        return $this->audit->transaction(function () use ($target, $data, $version, $actorId): Tugboat {
            $tugboat = VersionToken::lockAndAssert($target, $version);
            $tugboat->fill($data);

            if (! $tugboat->isDirty()) {
                return $tugboat;
            }

            $affectsPlanning = $tugboat->isDirty(self::PLANNING_ATTRIBUTES);
            $tugboat->save();
            $this->audit->recordUpdated($tugboat, $actorId);

            if ($affectsPlanning) {
                ResourceChanged::dispatch(ResourceKind::Tug, $tugboat->id, $actorId);
            }

            return $tugboat->refresh();
        });
    }
}
