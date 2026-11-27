<?php

namespace App\Modules\Yard\Services;

use App\Modules\Access\Models\User;
use App\Modules\MasterData\Models\StorageFacility;
use App\Modules\Shared\Audit\AuditService;
use App\Modules\Shared\Enums\AuditLogAction;
use App\Modules\Shared\Enums\StorageDetailKind;
use App\Modules\Shared\Support\VersionToken;
use App\Modules\Yard\Enums\ZoneDetail;
use App\Modules\Yard\Models\DgZoneAllowedClass;
use App\Modules\Yard\Models\YardZone;
use App\Modules\Yard\Models\YardZoneType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Arr;
use Illuminate\Validation\ValidationException;

/**
 * Yard zones with the detail their type needs (P2-F4, FR-MD-12). The zone and its detail rows are saved in one
 * transaction, and the zone version covers them. The zone type never changes. A zone is deleted only without blocks and
 * while no yard run of the terminal is in progress.
 */
class YardZoneService
{
    private const array REEFER_FIELDS = ['total_power_plugs', 'power_type'];

    private const array OOG_FIELDS = ['max_length', 'max_width', 'max_height', 'max_weight'];

    public function __construct(
        private readonly AuditService $audit,
        private readonly YardRunGuard $runs,
        private readonly YardRefusal $refusal,
    ) {}

    /**
     * @param  array<string, mixed>  $data  Validated by StoreYardZoneRequest.
     */
    public function create(StorageFacility $yard, array $data, int $actorId): YardZone
    {
        $type = YardZoneType::query()->findOrFail($data['yard_zone_type_id']);

        return $this->audit->transaction(function () use ($yard, $type, $data, $actorId): YardZone {
            try {
                $zone = YardZone::query()->create([
                    'container_yard_id' => $yard->id,
                    'detail_kind' => StorageDetailKind::ContainerYard,
                    'yard_zone_type_id' => $type->id,
                    'zone_detail' => $type->zone_detail,
                    'code' => $data['code'],
                ]);
            } catch (UniqueConstraintViolationException) {
                throw ValidationException::withMessages(['code' => ['validation.unique']]);
            }

            $zone->refresh();
            $this->audit->recordCreated($zone, $actorId);
            $this->saveDetail($zone, $data, $actorId);

            return $zone;
        });
    }

    /**
     * @param  array<string, mixed>  $data  Validated by UpdateYardZoneRequest, without the version.
     */
    public function update(YardZone $zone, array $data, string $version, int $actorId): YardZone
    {
        return $this->audit->transaction(function () use ($zone, $data, $version, $actorId): YardZone {
            $locked = VersionToken::lockAndAssert($zone, $version);
            $locked->fill(['code' => $data['code']]);
            $codeChanged = $locked->isDirty();

            if ($codeChanged) {
                try {
                    $locked->save();
                } catch (UniqueConstraintViolationException) {
                    throw ValidationException::withMessages(['code' => ['validation.unique']]);
                }

                $this->audit->recordUpdated($locked, $actorId);
            }

            if ($this->saveDetail($locked, $data, $actorId) && ! $codeChanged) {
                $locked->touch();
            }

            return $locked->refresh();
        });
    }

    public function delete(YardZone $zone, string $version, User $actor, string $route): void
    {
        $refused = $this->audit->transaction(function () use ($zone, $version, $actor): ?string {
            $locked = VersionToken::lockAndAssert($zone, $version);

            if ($locked->yardBlocks()->exists()) {
                return 'zone_has_blocks';
            }

            if ($this->runs->isRunInProgress($locked->containerYard->terminal_id)) {
                return 'run_in_progress';
            }

            $this->deleteDetail($locked, $actor->id);
            $locked->delete();
            $this->audit->recordDeleted($locked, $actor->id);

            return null;
        });

        if ($refused !== null) {
            $this->refusal->refuse($refused, $zone, $actor, $route);
        }
    }

    /**
     * @param  array<string, mixed>  $data
     * @return bool Whether a detail row changed.
     */
    private function saveDetail(YardZone $zone, array $data, int $actorId): bool
    {
        return match ($zone->zone_detail) {
            ZoneDetail::Reefer => $this->saveRow($zone->reeferZoneDetails(), $zone, Arr::only($data, self::REEFER_FIELDS), $actorId),
            ZoneDetail::Oog => $this->saveRow($zone->oogZoneDetails(), $zone, Arr::only($data, self::OOG_FIELDS), $actorId),
            ZoneDetail::DangerousGoods => $this->saveAllowedClasses($zone, $data['imdg_classes'], $actorId),
            ZoneDetail::None => false,
        };
    }

    /**
     * Create or change the single reefer or out-of-gauge row of the zone.
     *
     * @param  HasMany<covariant Model, YardZone>  $relation
     * @param  array<string, mixed>  $values
     */
    private function saveRow(HasMany $relation, YardZone $zone, array $values, int $actorId): bool
    {
        $row = $relation->first() ?? $relation->make(['zone_detail' => $zone->zone_detail]);
        $row->fill($values);

        if (! $row->exists) {
            $row->save();
            $this->audit->recordCreated($row, $actorId);

            return true;
        }

        if (! $row->isDirty()) {
            return false;
        }

        $row->save();
        $this->audit->recordUpdated($row, $actorId);

        return true;
    }

    /**
     * The allowed classes table has a two-column key, so its rows are written by query and audited as one list per zone.
     *
     * @param  list<string>  $classes
     */
    private function saveAllowedClasses(YardZone $zone, array $classes, int $actorId): bool
    {
        $current = DgZoneAllowedClass::query()->where('yard_zone_id', $zone->id)->orderBy('imdg_class')->pluck('imdg_class')->all();
        $wanted = collect($classes)->sort()->values()->all();

        if ($current === $wanted) {
            return false;
        }

        DgZoneAllowedClass::query()->where('yard_zone_id', $zone->id)->whereNotIn('imdg_class', $wanted)->delete();
        DgZoneAllowedClass::query()->insert(array_map(
            fn (string $class): array => ['yard_zone_id' => $zone->id, 'zone_detail' => ZoneDetail::DangerousGoods->value, 'imdg_class' => $class],
            array_values(array_diff($wanted, $current)),
        ));

        $this->audit->record(
            $current === [] ? AuditLogAction::Create : AuditLogAction::Update,
            'dg_zone_allowed_classes',
            $zone->id,
            $actorId,
            $current === [] ? null : ['imdg_classes' => $current],
            ['imdg_classes' => $wanted],
        );

        return true;
    }

    private function deleteDetail(YardZone $zone, int $actorId): void
    {
        foreach ([...$zone->reeferZoneDetails()->get(), ...$zone->oogZoneDetails()->get()] as $row) {
            $row->delete();
            $this->audit->recordDeleted($row, $actorId);
        }

        $classes = DgZoneAllowedClass::query()->where('yard_zone_id', $zone->id)->orderBy('imdg_class')->pluck('imdg_class')->all();

        if ($classes !== []) {
            DgZoneAllowedClass::query()->where('yard_zone_id', $zone->id)->delete();
            $this->audit->record(AuditLogAction::Delete, 'dg_zone_allowed_classes', $zone->id, $actorId, ['imdg_classes' => $classes], null);
        }
    }
}
