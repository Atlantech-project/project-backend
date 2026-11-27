<?php

namespace App\Modules\Yard\Http\Requests;

use App\Modules\Shared\Http\Pagination\PageRequest;
use App\Modules\Yard\Enums\YardSlotStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Query of GET /api/v1/terminals/{terminal}/yard-blocks/{yardBlock}/slots (P2-F4).
 */
class YardSlotIndexRequest extends FormRequest
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
            ...PageRequest::rules(),
            'bay' => ['sometimes', 'integer', 'min:1'],
            'row_no' => ['sometimes', 'integer', 'min:1'],
            'tier' => ['sometimes', 'integer', 'min:1'],
            'status' => ['sometimes', 'string', Rule::enum(YardSlotStatus::class)],
        ];
    }

    public function page(): PageRequest
    {
        return new PageRequest((int) $this->query('page', 1), (int) $this->query('per_page', PageRequest::DEFAULT_PER_PAGE));
    }
}
