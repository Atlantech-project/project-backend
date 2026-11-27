<?php

namespace App\Modules\MasterData\Http\Requests;

use App\Modules\Shared\Support\VersionToken;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Body of `PUT /api/v1/tug-masters/{tugMaster}/tugboat` (P2-F3, FR-MD-08): the profile's `version` and the tug, or null
 * to unassign.
 */
class AssignTugboatRequest extends FormRequest
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
            'version' => VersionToken::rules(),
            'tugboat_id' => ['present', 'nullable', 'integer', Rule::exists('tugboats', 'id')],
        ];
    }
}
