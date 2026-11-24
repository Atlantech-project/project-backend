<?php

namespace App\Modules\MasterData\Http\Requests;

use App\Modules\MasterData\Rules\GeoJsonPolygon;
use App\Modules\Shared\Enums\TerminalType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Body of `POST /api/v1/terminals` (P2-F2, FR-MD-03). Lengths follow the `terminals` columns; `code` and `terminal_type`
 * cannot change after creation.
 */
class StoreTerminalRequest extends FormRequest
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
            'code' => ['required', 'string', 'max:20', 'unique:terminals,code'],
            'name' => ['required', 'string', 'max:120', 'unique:terminals,name'],
            'terminal_type' => ['required', 'string', Rule::enum(TerminalType::class)],
            'boundary_coordinates' => ['nullable', 'array', new GeoJsonPolygon],
        ];
    }
}
