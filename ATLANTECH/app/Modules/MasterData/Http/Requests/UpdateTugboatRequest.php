<?php

namespace App\Modules\MasterData\Http\Requests;

use App\Modules\MasterData\Enums\TugPowerUnit;
use App\Modules\MasterData\Models\Tugboat;
use App\Modules\Shared\Support\VersionToken;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Body of `PATCH /api/v1/tugboats/{tugboat}` (P2-F3): the `version` and any field to change. A change of status keeps
 * the tug master link (Q10).
 */
class UpdateTugboatRequest extends FormRequest
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
        /** @var Tugboat $tugboat */
        $tugboat = $this->route('tugboat');

        return [
            'version' => VersionToken::rules(),
            'name' => ['sometimes', 'string', 'max:100', Rule::unique('tugboats', 'name')->ignore($tugboat->id)],
            'tug_power' => ['sometimes', 'decimal:0,2', 'gt:0', 'max:999999.99'],
            'tug_power_unit' => ['sometimes', Rule::in([TugPowerUnit::BollardPullTonnes->value])],
            'capacity' => ['sometimes', 'nullable', 'decimal:0,2', 'gt:0', 'max:999999.99'],
            'status' => ['sometimes', Rule::in(StoreTugboatRequest::settableStatuses())],
        ];
    }
}
