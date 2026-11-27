<?php

namespace App\Modules\MasterData\Http\Requests;

use App\Modules\Access\Http\Requests\StoreUserRequest;
use Illuminate\Validation\Rule;

/**
 * Body of `POST /api/v1/tug-masters` (P2-F3, FR-MD-08): the user fields of `POST /users` and an optional tug. The role
 * is fixed by the endpoint (Q5); a tug that already has a master is refused by the service (422, Q6).
 */
class StoreTugMasterRequest extends StoreUserRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            ...parent::rules(),
            'tugboat_id' => ['nullable', 'integer', Rule::exists('tugboats', 'id')],
        ];
    }
}
