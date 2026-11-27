<?php

namespace App\Modules\MasterData\Http\Resources;

use App\Modules\MasterData\Models\PortProfile;
use App\Modules\Shared\Support\VersionToken;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The port profile (P2-F1, P-26). `id` is the path segment of the update; `public_id`, `port_code` and
 * `governorate_code` are read-only.
 *
 * @mixin PortProfile
 */
class PortProfileResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'public_id' => $this->public_id,
            'port_code' => $this->port_code,
            'governorate_code' => $this->governorate_code,
            'name' => $this->name,
            'latitude' => (float) $this->latitude,
            'longitude' => (float) $this->longitude,
            'timezone' => $this->timezone,
            'boundary_coordinates' => $this->boundary_coordinates,
            'version' => VersionToken::of($this->resource),
        ];
    }
}
