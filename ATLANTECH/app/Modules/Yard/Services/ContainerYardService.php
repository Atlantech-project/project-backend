<?php

namespace App\Modules\Yard\Services;

use App\Modules\MasterData\Enums\FacilityStatus;
use App\Modules\MasterData\Models\StorageFacility;
use App\Modules\MasterData\Models\StorageType;
use App\Modules\MasterData\Models\Terminal;
use App\Modules\Shared\Audit\AuditService;
use App\Modules\Shared\Enums\StorageDetailKind;
use App\Modules\Shared\Enums\TerminalType;
use App\Modules\Shared\Support\VersionToken;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Arr;
use Illuminate\Validation\ValidationException;

/**
 * The container yard record of a container terminal: a storage facility of kind CONTAINER_YARD (P2-F4 Q1). It has no
 * capacity of its own (it is counted from the slots) and is never deleted; its status can be set to INACTIVE.
 */
class ContainerYardService
{
    private const array ATTRIBUTES = ['code', 'name', 'area', 'max_floor_load', 'status', 'latitude', 'longitude'];

    public function __construct(private readonly AuditService $audit) {}

    /**
     * @param  array<string, mixed>  $data  Validated by StoreContainerYardRequest.
     *
     * @throws ValidationException 422 on a non-container terminal or a code already used.
     */
    public function create(Terminal $terminal, array $data, int $actorId): StorageFacility
    {
        $this->assertContainerTerminal($terminal);
        $typeId = StorageType::query()->where('detail_kind', StorageDetailKind::ContainerYard)->orderBy('id')->firstOrFail()->id;

        return $this->audit->transaction(function () use ($terminal, $data, $actorId, $typeId): StorageFacility {
            try {
                $yard = $terminal->storageFacilities()->create([
                    ...Arr::only($data, self::ATTRIBUTES),
                    'status' => $data['status'] ?? FacilityStatus::Active,
                    'terminal_type' => TerminalType::Container,
                    'storage_type_id' => $typeId,
                    'detail_kind' => StorageDetailKind::ContainerYard,
                ]);
            } catch (UniqueConstraintViolationException) {
                throw ValidationException::withMessages(['code' => ['validation.unique']]);
            }

            $yard->refresh();
            $this->audit->recordCreated($yard, $actorId);

            return $yard;
        });
    }

    /**
     * @param  array<string, mixed>  $data  Validated by UpdateContainerYardRequest, without the version.
     */
    public function update(StorageFacility $yard, array $data, string $version, int $actorId): StorageFacility
    {
        return $this->audit->transaction(function () use ($yard, $data, $version, $actorId): StorageFacility {
            $locked = VersionToken::lockAndAssert($yard, $version);
            $locked->fill(Arr::only($data, self::ATTRIBUTES));

            try {
                $locked->save();
            } catch (UniqueConstraintViolationException) {
                throw ValidationException::withMessages(['code' => ['validation.unique']]);
            }

            $this->audit->recordUpdated($locked, $actorId);

            return $locked->refresh();
        });
    }

    private function assertContainerTerminal(Terminal $terminal): void
    {
        if ($terminal->terminal_type !== TerminalType::Container) {
            throw ValidationException::withMessages(['terminal' => ['errors.yard.not_container_terminal']]);
        }
    }
}
