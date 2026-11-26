<?php

namespace App\Modules\MasterData\Http\Requests;

use App\Modules\Shared\Support\VersionToken;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Body of `PUT /api/v1/berths/{berth}` (P2-F2): every field optional (a field that is not sent keeps its value), with
 * the berth's `version`. When `length` and `max_vessel_loa` are both sent they are compared here; when only one is sent,
 * `BerthService` compares it with the stored other one. `code` and `status` are refused (422): the code is the berth's
 * identifier and the status has its own endpoints. A sent `terminal_ids` replaces the list and keeps at least one.
 */
class UpdateBerthRequest extends FormRequest
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
            'name' => ['sometimes', 'nullable', 'string', 'max:80'],
            ...BerthRules::dimensions(required: false, loaWithinLength: $this->has('length')),
            'terminal_ids' => ['sometimes', 'array', 'min:1'],
            'terminal_ids.*' => ['integer', 'distinct', 'exists:terminals,id'],
            'code' => ['prohibited'],
            'status' => ['prohibited'],
            'version' => VersionToken::rules(),
        ];
    }
}
