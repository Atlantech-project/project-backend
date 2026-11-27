<?php

namespace App\Modules\MasterData\Http\Requests;

use App\Modules\Shared\Support\VersionToken;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Body of `PUT /api/v1/vessels/{vessel}` (P3-H7, FR-BQ-13): every particular optional (a field that is not sent keeps its
 * value), with the vessel's `version`. `imo_number` is refused (422): the system finds a vessel by it, and the lead ruled
 * that it is not edited.
 */
class UpdateVesselRequest extends FormRequest
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
            ...VesselRules::particulars(required: false),
            'imo_number' => ['prohibited'],
            'version' => VersionToken::rules(),
        ];
    }
}
