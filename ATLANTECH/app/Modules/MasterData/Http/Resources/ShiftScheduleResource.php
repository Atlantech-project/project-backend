<?php

namespace App\Modules\MasterData\Http\Resources;

use App\Modules\MasterData\Models\PilotSchedule;
use App\Modules\MasterData\Models\TugMasterSchedule;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One pilot or tug master schedule entry (P2-F3): the person (with `is_active`, Q4), the port-local date and the shift.
 *
 * @property PilotSchedule|TugMasterSchedule $resource
 */
class ShiftScheduleResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $schedule = $this->resource;
        $profile = $schedule instanceof PilotSchedule ? $schedule->pilotUser : $schedule->tugMasterUser;

        return [
            'id' => $schedule->id,
            'user_id' => $profile->user_id,
            'name' => $profile->user->name,
            'is_active' => $profile->user->is_active,
            'shift_date' => $schedule->shift_date->toDateString(),
            'port_shift' => [
                'id' => $schedule->portShift->id,
                'code' => $schedule->portShift->code,
                'start_time' => substr($schedule->portShift->start_time, 0, 5),
                'end_time' => substr($schedule->portShift->end_time, 0, 5),
            ],
        ];
    }
}
