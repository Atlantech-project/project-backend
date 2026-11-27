<?php

namespace App\Modules\MasterData\Http\Requests;

use App\Modules\MasterData\Enums\PilotRank;
use App\Modules\Shared\Support\VersionToken;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Body of `PATCH /api/v1/pilots/{pilot}` and `PATCH /api/v1/tug-masters/{tugMaster}` (P2-F3): the profile's `version`,
 * name and phone, and for a pilot the rank (promotion, FR-MD-09).
 */
class UpdateCrewMemberRequest extends FormRequest
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
        $rules = [
            'version' => VersionToken::rules(),
            'name' => ['sometimes', 'string', 'max:120'],
            'phone' => ['sometimes', 'nullable', 'string', 'max:30'],
        ];

        if ($this->route()?->hasParameter('pilot')) {
            $rules['pilot_rank'] = ['sometimes', Rule::enum(PilotRank::class)];
        }

        return $rules;
    }
}
