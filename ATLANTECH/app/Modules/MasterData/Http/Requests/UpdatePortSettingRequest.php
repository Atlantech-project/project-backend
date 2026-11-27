<?php

namespace App\Modules\MasterData\Http\Requests;

use App\Modules\Shared\Support\VersionToken;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Body of `PUT /api/v1/port-settings/{setting}` (P2-F1, CT-18 §4): the new `value` and the setting's `version`. The value
 * is checked against the setting's type by `PortSettingService`. Everything else comes from the seeds and is refused.
 */
class UpdatePortSettingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'value' => ['present'],
            'setting_key' => ['prohibited'],
            'value_type' => ['prohibited'],
            'min' => ['prohibited'],
            'max' => ['prohibited'],
            'description' => ['prohibited'],
            'updated_by' => ['prohibited'],
            'version' => VersionToken::rules(),
        ];
    }
}
