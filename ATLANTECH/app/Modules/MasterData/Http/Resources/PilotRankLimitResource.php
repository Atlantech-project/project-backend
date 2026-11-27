<?php

namespace App\Modules\MasterData\Http\Resources;

use App\Modules\MasterData\Models\PilotRankLimit;
use App\Modules\Shared\Support\VersionToken;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One rank limit (P2-F3): the largest vessel LOA in metres the rank may pilot, null for no limit.
 *
 * @mixin PilotRankLimit
 */
class PilotRankLimitResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'pilot_rank' => $this->pilot_rank,
            'max_vessel_loa' => $this->max_vessel_loa,
            'version' => VersionToken::of($this->resource),
        ];
    }
}
