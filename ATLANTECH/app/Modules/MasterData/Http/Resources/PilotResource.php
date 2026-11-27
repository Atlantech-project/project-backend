<?php

namespace App\Modules\MasterData\Http\Resources;

use App\Modules\MasterData\Models\PilotProfile;
use App\Modules\Shared\Support\VersionToken;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One pilot (P2-F3): the user and the profile. `id` is the user id; `version` is the profile's, sent back on every change.
 *
 * @mixin PilotProfile
 */
class PilotResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->user_id,
            'name' => $this->user->name,
            'username' => $this->user->username,
            'phone' => $this->user->phone,
            'is_active' => $this->user->is_active,
            'pilot_rank' => $this->pilot_rank,
            'version' => VersionToken::of($this->resource),
        ];
    }
}
