<?php

namespace App\Modules\MasterData\Http\Requests;

use App\Modules\MasterData\Rules\GeoJsonPolygon;
use App\Modules\Shared\Support\VersionToken;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Body of `PUT /api/v1/port-profile/{portProfile}` (P2-F1, P-26): the fields the Port Admin owns, each optional (a field
 * that is not sent keeps its value), with the profile's `version`. `id`, `public_id`, `port_code` and `governorate_code`
 * come from the SaaS registry and are refused (422).
 */
class UpdatePortProfileRequest extends FormRequest
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
            'name' => ['sometimes', 'required', 'string', 'max:150'],
            'latitude' => ['sometimes', 'required', 'numeric', 'decimal:0,6', 'between:-90,90'],
            'longitude' => ['sometimes', 'required', 'numeric', 'decimal:0,6', 'between:-180,180'],
            'timezone' => ['sometimes', 'required', 'string', 'max:64', 'timezone:all'],
            'boundary_coordinates' => ['sometimes', 'nullable', 'array', new GeoJsonPolygon],
            'id' => ['prohibited'],
            'public_id' => ['prohibited'],
            'port_code' => ['prohibited'],
            'governorate_code' => ['prohibited'],
            'version' => VersionToken::rules(),
        ];
    }
}
