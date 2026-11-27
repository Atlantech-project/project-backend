<?php

namespace App\Modules\MasterData\Http\Requests;

use App\Modules\Shared\Support\VersionToken;

/**
 * Body of `PUT /api/v1/terminals/{terminal}/productivity-profiles/{terminalProductivityProfile}` (P2-F6): every field
 * optional (a field that is not sent keeps its value), with the profile's `version`.
 */
class UpdateProductivityProfileRequest extends ProductivityProfileWriteRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            ...ProductivityProfileRules::fields(required: false),
            'version' => VersionToken::rules(),
        ];
    }
}
