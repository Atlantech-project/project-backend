<?php

namespace App\Modules\MasterData\Http\Resources;

use App\Modules\MasterData\Enums\PortSettingValueType;
use App\Modules\MasterData\Models\PortSetting;
use App\Modules\Shared\Support\VersionToken;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One port setting (P2-F1, P-62). `value` has the JSON type of `value_type`; `updated_by` is null while the value is the
 * seeded default.
 *
 * @mixin PortSetting
 */
class PortSettingResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'setting_key' => $this->setting_key,
            'value_type' => $this->value_type,
            'value' => match ($this->value_type) {
                PortSettingValueType::Int => (int) $this->setting_value,
                PortSettingValueType::Decimal => (float) $this->setting_value,
                PortSettingValueType::Bool => in_array($this->setting_value, ['true', '1'], true),
                PortSettingValueType::String => $this->setting_value,
                PortSettingValueType::Json => json_decode($this->setting_value),
            },
            'min' => $this->min_value === null ? null : (float) $this->min_value,
            'max' => $this->max_value === null ? null : (float) $this->max_value,
            'description' => $this->description,
            'updated_by' => $this->updated_by,
            'version' => VersionToken::of($this->resource),
        ];
    }
}
