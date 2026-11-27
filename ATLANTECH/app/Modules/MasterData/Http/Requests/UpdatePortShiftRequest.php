<?php

namespace App\Modules\MasterData\Http\Requests;

use App\Modules\MasterData\Models\PortShift;
use App\Modules\Shared\Support\VersionToken;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Body of `PATCH /api/v1/port-shifts/{portShift}` (P2-F3): the `version` and any field to change. Equal start and end
 * times are refused also when only one of them is sent.
 */
class UpdatePortShiftRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'version' => VersionToken::rules(),
            'code' => ['sometimes', 'string', 'max:20', Rule::unique('port_shifts', 'code')->ignore($this->shift()->id)],
            'name' => ['sometimes', 'string', 'max:60'],
            'start_time' => ['sometimes', 'date_format:H:i'],
            'end_time' => ['sometimes', 'date_format:H:i'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return list<callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->hasAny(['start_time', 'end_time'])) {
                    return;
                }

                $start = $this->input('start_time', substr($this->shift()->start_time, 0, 5));
                $end = $this->input('end_time', substr($this->shift()->end_time, 0, 5));

                if ($start === $end) {
                    $validator->errors()->add('end_time', 'validation.different');
                }
            },
        ];
    }

    private function shift(): PortShift
    {
        return $this->route('portShift');
    }
}
