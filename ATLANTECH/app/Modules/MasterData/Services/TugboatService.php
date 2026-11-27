<?php

namespace App\Modules\MasterData\Services;

use App\Modules\MasterData\Enums\TugboatStatus;
use App\Modules\MasterData\Models\Tugboat;
use App\Modules\Shared\Audit\AuditService;
use App\Modules\Shared\Support\VersionToken;

/**
 * The tug fleet of the Tug Manager (P2-F3, FR-MD-07). Deactivating a tug is setting `INACTIVE`; the row and its tug
 * master link are kept (Q10). P2-F7 dispatches `ResourceChanged` (TUG) after a saved change here.
 */
class TugboatService
{
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

            $tugboat->save();
            $this->audit->recordUpdated($tugboat, $actorId);

            return $tugboat->refresh();
        });
    }
}
