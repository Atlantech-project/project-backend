<?php

namespace App\Modules\Access\Http\Resources;

use App\Modules\Access\Models\UserDevice;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A device of a user (P-25). The push token itself is never returned, only whether one is registered.
 * Load it with `UserDevice::withLiveTokenCount()`.
 *
 * @mixin UserDevice
 */
class UserDeviceResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'device_id' => $this->device_id,
            'platform' => $this->platform->value,
            'app_version' => $this->app_version,
            'last_seen_at' => $this->last_seen_at,
            'revoked_at' => $this->revoked_at,
            'has_push_token' => $this->push_token !== null,
            'live_token_count' => (int) $this->getAttribute('live_token_count'),
        ];
    }
}
