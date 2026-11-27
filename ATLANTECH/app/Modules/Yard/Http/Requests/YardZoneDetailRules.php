<?php

namespace App\Modules\Yard\Http\Requests;

use App\Modules\Yard\Enums\ZoneDetail;

/**
 * Which detail fields a zone takes depends on the zone_detail of its type (P2-F4): reefer plugs, the allowed IMDG classes
 * of a dangerous goods zone, or the out-of-gauge limits. The fields of the other kinds are refused.
 */
class YardZoneDetailRules
{
    private const array FIELDS = ['total_power_plugs', 'power_type', 'imdg_classes', 'max_length', 'max_width', 'max_height', 'max_weight'];

    /**
     * @return array<string, mixed>
     */
    public static function for(?ZoneDetail $detail): array
    {
        $allowed = match ($detail) {
            ZoneDetail::Reefer => [
                'total_power_plugs' => ['required', 'integer', 'min:1', 'max:100000'],
                'power_type' => ['present', 'nullable', 'string', 'max:30'],
            ],
            ZoneDetail::DangerousGoods => [
                'imdg_classes' => ['required', 'list', 'min:1'],
                'imdg_classes.*' => ['string', 'distinct', 'exists:imdg_classes,code'],
            ],
            ZoneDetail::Oog => [
                'max_length' => self::limit(9999.99),
                'max_width' => self::limit(9999.99),
                'max_height' => self::limit(9999.99),
                'max_weight' => self::limit(999999.99),
            ],
            default => [],
        };

        $refused = [];

        foreach (self::FIELDS as $field) {
            if (! array_key_exists($field, $allowed)) {
                $refused[$field] = ['prohibited'];
            }
        }

        return [...$refused, ...$allowed];
    }

    /**
     * An out-of-gauge limit is always sent, and may be null (no limit).
     *
     * @return list<string>
     */
    private static function limit(float $max): array
    {
        return ['present', 'nullable', 'numeric', 'decimal:0,2', 'gt:0', 'max:'.$max];
    }
}
