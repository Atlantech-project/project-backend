<?php

namespace App\Modules\MasterData\Services;

use App\Modules\MasterData\Models\Equipment;
use App\Modules\MasterData\Models\Terminal;
use App\Modules\Shared\Audit\AuditService;
use App\Modules\Shared\Enums\ResourceKind;
use App\Modules\Shared\Enums\TerminalType;
use App\Modules\Shared\Events\ResourceChanged;
use App\Modules\Shared\Support\VersionToken;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Arr;
use Illuminate\Validation\ValidationException;

/**
 * Creating and editing the equipment of a container terminal with its capabilities (P2-F4, FR-MD-11). The equipment row's
 * version covers its capabilities, so a change to a capability alone still moves `updated_at`. Equipment is never deleted.
 * New equipment, and a saved change of quantities or capabilities, dispatch `ResourceChanged` (P2-F7).
 */
class EquipmentService
{
    private const array QUANTITIES = ['quantity', 'maintenance_quantity', 'blocked_quantity'];

    public function __construct(private readonly AuditService $audit) {}

    /**
     * @param  array{equipment_category_id: int|string, quantity: int|string, maintenance_quantity: int|string, blocked_quantity: int|string, capabilities: list<array{capability_type_id: int|string, value: int|float|string}>}  $data
     *
     * @throws ValidationException 422 when the terminal is not a container terminal.
     */
    public function create(Terminal $terminal, array $data, int $actorId): Equipment
    {
        $this->assertContainerTerminal($terminal);

        return $this->audit->transaction(function () use ($terminal, $data, $actorId): Equipment {
            try {
                $equipment = $terminal->equipment()->create(Arr::only($data, ['equipment_category_id', ...self::QUANTITIES]));
            } catch (UniqueConstraintViolationException) {
                throw ValidationException::withMessages(['equipment_category_id' => ['validation.unique']]);
            }
            $equipment->refresh();
            $this->audit->recordCreated($equipment, $actorId);
            $this->syncCapabilities($equipment, $data['capabilities'], $actorId);
            ResourceChanged::dispatch(ResourceKind::Equipment, $equipment->id, $actorId);

            return $equipment;
        });
    }

    /**
     * @param  array{quantity: int|string, maintenance_quantity: int|string, blocked_quantity: int|string, capabilities: list<array{capability_type_id: int|string, value: int|float|string}>}  $data
     *
     * @throws ValidationException 422 when the terminal is not a container terminal.
     */
    public function update(Terminal $terminal, Equipment $equipment, array $data, string $version, int $actorId): Equipment
    {
        $this->assertContainerTerminal($terminal);

        return $this->audit->transaction(function () use ($equipment, $data, $version, $actorId): Equipment {
            $locked = VersionToken::lockAndAssert($equipment, $version);

            $locked->fill(Arr::only($data, self::QUANTITIES));
            $quantitiesChanged = $locked->isDirty();

            if ($quantitiesChanged) {
                $locked->save();
                $this->audit->recordUpdated($locked, $actorId);
            }

            $capabilitiesChanged = $this->syncCapabilities($locked, $data['capabilities'], $actorId);

            if ($capabilitiesChanged && ! $quantitiesChanged) {
                $locked->touch();
            }

            if ($quantitiesChanged || $capabilitiesChanged) {
                ResourceChanged::dispatch(ResourceKind::Equipment, $locked->id, $actorId);
            }

            return $locked->refresh();
        });
    }

    private function assertContainerTerminal(Terminal $terminal): void
    {
        if ($terminal->terminal_type !== TerminalType::Container) {
            throw ValidationException::withMessages(['terminal' => ['errors.equipment.not_container_terminal']]);
        }
    }

    /**
     * Make the stored capabilities match the sent list, auditing each row that is removed, added or changed.
     *
     * @param  list<array{capability_type_id: int|string, value: int|float|string}>  $capabilities
     * @return bool Whether any row changed.
     */
    private function syncCapabilities(Equipment $equipment, array $capabilities, int $actorId): bool
    {
        $wanted = collect($capabilities)->keyBy(fn (array $capability): int => (int) $capability['capability_type_id']);
        $current = $equipment->equipmentCapabilities()->get()->keyBy('capability_type_id')->toBase();
        $changed = false;

        foreach ($current->diffKeys($wanted) as $removed) {
            $removed->delete();
            $this->audit->recordDeleted($removed, $actorId);
            $changed = true;
        }

        foreach ($wanted as $typeId => $item) {
            $capability = $current->get($typeId) ?? $equipment->equipmentCapabilities()->make(['capability_type_id' => $typeId]);
            $capability->capability_value = $item['value'];

            if (! $capability->exists) {
                $capability->save();
                $this->audit->recordCreated($capability, $actorId);
                $changed = true;
            } elseif ($capability->isDirty()) {
                $capability->save();
                $this->audit->recordUpdated($capability, $actorId);
                $changed = true;
            }
        }

        return $changed;
    }
}
