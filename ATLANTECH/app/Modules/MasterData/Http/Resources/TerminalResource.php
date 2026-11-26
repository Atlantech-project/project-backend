<?php

namespace App\Modules\MasterData\Http\Resources;

use App\Modules\MasterData\Models\Berth;
use App\Modules\MasterData\Models\Terminal;
use App\Modules\Shared\Support\VersionToken;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A terminal (P2-F2, P-27). `berths_count` is present in the list (how many berths the terminal may use, shared ones
 * included); `berths` is present when loaded, as a short list (id, code, name, status).
 *
 * @mixin Terminal
 */
class TerminalResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'name' => $this->name,
            'terminal_type' => $this->terminal_type,
            'boundary_coordinates' => $this->boundary_coordinates,
            'is_active' => $this->is_active,
            'version' => VersionToken::of($this->resource),
            'berths_count' => $this->whenCounted('berths'),
            'berths' => $this->whenLoaded('berths', fn () => $this->berths->map(fn (Berth $berth): array => [
                'id' => $berth->id,
                'code' => $berth->code,
                'name' => $berth->name,
                'status' => $berth->status,
            ])->values()),
        ];
    }
}
