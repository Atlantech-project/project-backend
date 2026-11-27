<?php

namespace App\Modules\MasterData\Http\Requests;

use App\Modules\Access\Http\Requests\StoreUserRequest;
use App\Modules\MasterData\Enums\PilotRank;
use Illuminate\Validation\Rule;

/**
 * Body of `POST /api/v1/pilots` (P2-F3, FR-MD-09): the user fields of `POST /users` and the rank. The role is fixed
 * by the endpoint (Q5).
 */
class StorePilotRequest extends StoreUserRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            ...parent::rules(),
            'pilot_rank' => ['required', Rule::enum(PilotRank::class)],
        ];
    }
}
