<?php

namespace App\Modules\MasterData\Http\Requests;

/**
 * Body of `POST /api/v1/terminals/{terminal}/productivity-profiles` (P2-F6). `vessel_type_id` left out or null is the
 * terminal's default profile; `valid_to` left out or null leaves the period open.
 */
class StoreProductivityProfileRequest extends ProductivityProfileWriteRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return ProductivityProfileRules::fields(required: true);
    }
}
