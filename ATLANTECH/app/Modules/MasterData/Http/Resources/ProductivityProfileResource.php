<?php

namespace App\Modules\MasterData\Http\Resources;

use App\Modules\MasterData\Models\TerminalProductivityProfile;
use App\Modules\Shared\Support\VersionToken;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One terminal productivity profile (P2-F6, P-48). `vessel_type_id` null is the terminal default; dates are whole days,
 * both included, and `valid_to` null leaves the period open.
 *
 * @mixin TerminalProductivityProfile
 */
class ProductivityProfileResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'terminal_id' => $this->terminal_id,
            'vessel_type_id' => $this->vessel_type_id,
            'productivity_rate' => (float) $this->productivity_rate,
            'rate_unit' => $this->rate_unit,
            'fixed_overhead_minutes' => (int) $this->fixed_overhead_minutes,
            'valid_from' => $this->valid_from->format('Y-m-d'),
            'valid_to' => $this->valid_to?->format('Y-m-d'),
            'version' => VersionToken::of($this->resource),
        ];
    }
}
