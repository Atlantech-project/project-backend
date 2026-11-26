<?php

namespace App\Modules\MasterData\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Body of `POST /api/v1/port-shifts` (P2-F3): times in port local time as `HH:MM`; an end before the start means the
 * shift ends the next day; equal times are refused (`chk_port_shift_times`). The overlap rule is in the service.
 */
class StorePortShiftRequest extends FormRequest
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
            'code' => ['required', 'string', 'max:20', Rule::unique('port_shifts', 'code')],
            'name' => ['required', 'string', 'max:60'],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['required', 'date_format:H:i', 'different:start_time'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
