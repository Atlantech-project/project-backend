<?php

namespace App\Modules\MasterData\Http\Resources;

use App\Modules\MasterData\Models\Vessel;
use App\Modules\Shared\Support\VersionToken;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A vessel's particulars (P3-H7, P-33). Sizes are JSON numbers in metres. Block coefficient, GT and DWT are per call and
 * belong to the berthing request.
 *
 * @mixin Vessel
 */
class VesselResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'imo_number' => $this->imo_number,
            'name' => $this->name,
            'vessel_type_id' => $this->vessel_type_id,
            'vessel_type' => [
                'id' => $this->vesselType->id,
                'code' => $this->vesselType->code,
                'name' => $this->vesselType->name,
            ],
            'loa' => (float) $this->loa,
            'beam' => (float) $this->beam,
            'max_draft' => (float) $this->max_draft,
            'net_tonnage' => $this->net_tonnage,
            'moulded_depth' => $this->moulded_depth === null ? null : (float) $this->moulded_depth,
            'call_sign' => $this->call_sign,
            'flag_state' => $this->flag_state,
            'version' => VersionToken::of($this->resource),
        ];
    }
}
