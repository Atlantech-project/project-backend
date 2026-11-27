<?php

namespace App\Modules\Yard\Http\Requests;

use App\Modules\Yard\Enums\YardSlotStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Body of POST /api/v1/terminals/{terminal}/yard-blocks/{yardBlock}/stack-status (P2-F4, DEC-29 rule 11): every tier of one bay and row.
 */
class YardStackStatusRequest extends FormRequest
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
            'bay' => ['required', 'integer', 'min:1'],
            'row_no' => ['required', 'integer', 'min:1'],
            'status' => ['required', 'string', Rule::enum(YardSlotStatus::class)],
        ];
    }
}
