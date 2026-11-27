<?php

namespace App\Modules\MasterData\Http\Requests;

use App\Modules\Shared\Support\VersionToken;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Body of `PUT /api/v1/navigation-channels/{channel}/waypoints` (P2-F1, P-26): the whole list in travel order, with the
 * channel's `version`. Adding, reordering and removing are all done by sending the new list; the position in the list is
 * the `sequence_order`, so the client does not send it. An empty list removes every waypoint.
 */
class ReplaceChannelWaypointsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Labels are compared without case, like the `uq_channel_waypoint_label` index.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'waypoints' => ['present', 'array', 'list'],
            'waypoints.*' => ['array'],
            'waypoints.*.label' => ['required', 'string', 'max:60', 'distinct:ignore_case'],
            'waypoints.*.latitude' => ['required', 'numeric', 'decimal:0,6', 'between:-90,90'],
            'waypoints.*.longitude' => ['required', 'numeric', 'decimal:0,6', 'between:-180,180'],
            'waypoints.*.sequence_order' => ['prohibited'],
            'version' => VersionToken::rules(),
        ];
    }
}
