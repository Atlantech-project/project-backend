<?php

namespace App\Modules\Yard\Http\Resources;

use App\Modules\Shared\Support\VersionToken;
use App\Modules\Yard\Enums\YardSlotStatus;
use App\Modules\Yard\Enums\ZoneDetail;
use App\Modules\Yard\Models\YardZone;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A yard zone with the detail of its kind and its capacity counted from the slots (P2-F4). Load RELATIONS and
 * slotCounts() first.
 *
 * @mixin YardZone
 */
class YardZoneResource extends JsonResource
{
    public const array RELATIONS = ['yardZoneType', 'reeferZoneDetails', 'oogZoneDetails', 'dgZoneAllowedClasses'];

    /**
     * @return array<int|string, mixed> For withCount() or loadCount().
     */
    public static function slotCounts(): array
    {
        return [
            'yardBlocks',
            'yardSlots',
            'yardSlots as blocked_slots_count' => fn (Builder $query) => $query->where('status', YardSlotStatus::Blocked),
            'yardSlots as occupied_slots_count' => fn (Builder $query) => $query->whereNotNull('container_id'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'container_yard_id' => $this->container_yard_id,
            'code' => $this->code,
            'zone_type' => [
                'id' => $this->yardZoneType->id,
                'code' => $this->yardZoneType->code,
                'name' => $this->yardZoneType->name,
            ],
            'zone_detail' => $this->zone_detail,
            'detail' => $this->detail(),
            'blocks_count' => (int) $this->yard_blocks_count,
            'capacity' => [
                'slots' => (int) $this->yard_slots_count,
                'blocked' => (int) $this->blocked_slots_count,
                'occupied' => (int) $this->occupied_slots_count,
            ],
            'version' => VersionToken::of($this->resource),
        ];
    }

    /**
     * @return array<string, mixed>|null Null for a zone with no detail.
     */
    private function detail(): ?array
    {
        $number = fn (?string $value): ?float => $value === null ? null : (float) $value;

        return match ($this->zone_detail) {
            ZoneDetail::Reefer => [
                'total_power_plugs' => $this->reeferZoneDetails->first()?->total_power_plugs,
                'power_type' => $this->reeferZoneDetails->first()?->power_type,
            ],
            ZoneDetail::DangerousGoods => [
                'imdg_classes' => $this->dgZoneAllowedClasses->pluck('imdg_class')->sort()->values()->all(),
            ],
            ZoneDetail::Oog => [
                'max_length' => $number($this->oogZoneDetails->first()?->max_length),
                'max_width' => $number($this->oogZoneDetails->first()?->max_width),
                'max_height' => $number($this->oogZoneDetails->first()?->max_height),
                'max_weight' => $number($this->oogZoneDetails->first()?->max_weight),
            ],
            ZoneDetail::None => null,
        };
    }
}
