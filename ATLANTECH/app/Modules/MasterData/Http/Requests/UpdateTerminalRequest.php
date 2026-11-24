<?php

namespace App\Modules\MasterData\Http\Requests;

use App\Modules\MasterData\Models\Terminal;
use App\Modules\MasterData\Rules\GeoJsonPolygon;
use App\Modules\Shared\Support\VersionToken;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Body of `PUT /api/v1/terminals/{terminal}` (P2-F2): `name` and `boundary_coordinates`, each optional (a field that is
 * not sent keeps its value), with the terminal's `version`. `code`, `terminal_type` and `is_active` are refused (422):
 * the type is part of composite foreign keys, and activation has its own endpoints.
 */
class UpdateTerminalRequest extends FormRequest
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
        $terminal = $this->route('terminal');

        return [
            'name' => ['sometimes', 'required', 'string', 'max:120', Rule::unique('terminals', 'name')->ignore($terminal instanceof Terminal ? $terminal->id : null)],
            'boundary_coordinates' => ['sometimes', 'nullable', 'array', new GeoJsonPolygon],
            'code' => ['prohibited'],
            'terminal_type' => ['prohibited'],
            'is_active' => ['prohibited'],
            'version' => VersionToken::rules(),
        ];
    }
}
