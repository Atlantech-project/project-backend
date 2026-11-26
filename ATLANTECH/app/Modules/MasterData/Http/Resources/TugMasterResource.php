<?php

namespace App\Modules\MasterData\Http\Resources;

use App\Modules\MasterData\Models\TugMasterProfile;
use App\Modules\Shared\Support\VersionToken;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One tug master (P2-F3): the user, the profile and the tug, or null when unassigned. `id` is the user id; `version` is
 * the profile's.
 *
 * @mixin TugMasterProfile
 */
class TugMasterResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $tugboat = $this->assignedTugboat;

        return [
            'id' => $this->user_id,
            'name' => $this->user->name,
            'username' => $this->user->username,
            'phone' => $this->user->phone,
            'is_active' => $this->user->is_active,
            'tugboat' => $tugboat === null ? null : [
                'id' => $tugboat->id,
                'name' => $tugboat->name,
                'status' => $tugboat->status,
            ],
            'version' => VersionToken::of($this->resource),
        ];
    }
}
