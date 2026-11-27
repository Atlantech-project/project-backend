<?php

namespace App\Modules\Yard\Http\Requests;

use App\Modules\Shared\Support\VersionToken;
use App\Modules\Yard\Enums\YardSlotStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Body of PUT /api/v1/terminals/{terminal}/yard-slots/{yardSlot} (P2-F4): the administrative status only.
 */
class UpdateYardSlotRequest extends FormRequest
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
            'status' => ['required', 'string', Rule::enum(YardSlotStatus::class)],
            'container_id' => ['prohibited'],
            'bay' => ['prohibited'],
            'row_no' => ['prohibited'],
            'tier' => ['prohibited'],
            'version' => VersionToken::rules(),
        ];
    }
}
