<?php

namespace App\Modules\MasterData\Http\Requests;

use App\Modules\Shared\Http\Pagination\PageRequest;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * Query string of `GET /api/v1/pilot-schedules` and `GET /api/v1/tug-master-schedules` (P2-F3, Q7): a port-local date
 * range of at most 62 days, optional person and shift, and the page.
 */
class ShiftScheduleIndexRequest extends FormRequest
{
    public const int MAX_DAYS = 62;

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
            ...PageRequest::rules(),
            'from' => ['required', 'date_format:Y-m-d'],
            'to' => ['required', 'date_format:Y-m-d', 'after_or_equal:from'],
            'user_id' => ['sometimes', 'integer'],
            'port_shift_id' => ['sometimes', 'integer'],
        ];
    }

    /**
     * @return list<callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->hasAny(['from', 'to'])) {
                    return;
                }

                $from = CarbonImmutable::createFromFormat('!Y-m-d', $this->query('from'));
                $to = CarbonImmutable::createFromFormat('!Y-m-d', $this->query('to'));

                if ($from->diffInDays($to) >= self::MAX_DAYS) {
                    $validator->errors()->add('to', 'validation.before');
                }
            },
        ];
    }

    public function page(): PageRequest
    {
        return new PageRequest((int) $this->query('page', 1), (int) $this->query('per_page', PageRequest::DEFAULT_PER_PAGE));
    }
}
