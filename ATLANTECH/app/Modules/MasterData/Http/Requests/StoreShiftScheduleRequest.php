<?php

namespace App\Modules\MasterData\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Body of `POST /api/v1/pilot-schedules` and `POST /api/v1/tug-master-schedules` (P2-F3, Q7): a batch of entries, all
 * saved or none. The person must have the profile of the endpoint; the rules on dates, roles and shifts are in the service.
 */
class StoreShiftScheduleRequest extends FormRequest
{
    public const int MAX_ENTRIES = 100;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $profiles = $this->routeIs('pilot-schedules.*') ? 'pilot_profiles' : 'tug_master_profiles';

        return [
            'entries' => ['required', 'array', 'min:1', 'max:'.self::MAX_ENTRIES],
            'entries.*' => ['required', 'array:user_id,shift_date,port_shift_id'],
            'entries.*.user_id' => ['required', 'integer', Rule::exists($profiles, 'user_id')],
            'entries.*.shift_date' => ['required', 'date_format:Y-m-d'],
            'entries.*.port_shift_id' => ['required', 'integer', Rule::exists('port_shifts', 'id')],
        ];
    }
}
