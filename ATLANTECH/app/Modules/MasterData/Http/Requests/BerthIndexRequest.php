<?php

namespace App\Modules\MasterData\Http\Requests;

use App\Modules\MasterData\Enums\FacilityStatus;
use App\Modules\Shared\Http\Pagination\PageRequest;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Query string of `GET /api/v1/berths` (P2-F2): page, `status`, `terminal_id` (scope checked by `TerminalScope::authorize`)
 * and the text search `q` (code or name).
 */
class BerthIndexRequest extends FormRequest
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
            'status' => ['sometimes', 'string', Rule::enum(FacilityStatus::class)],
            'terminal_id' => ['sometimes', 'integer', 'min:1'],
            'q' => ['sometimes', 'string', 'min:1', 'max:100'],
        ];
    }

    public function page(): PageRequest
    {
        return new PageRequest((int) $this->query('page', 1), (int) $this->query('per_page', PageRequest::DEFAULT_PER_PAGE));
    }
}
