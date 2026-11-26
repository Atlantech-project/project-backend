<?php

namespace App\Modules\MasterData\Http\Requests;

use App\Modules\Shared\Enums\TerminalType;
use App\Modules\Shared\Http\Pagination\PageRequest;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Query string of `GET /api/v1/terminals` (P2-F2): page, `terminal_type`, `is_active` and the text search `q` (code or name).
 */
class TerminalIndexRequest extends FormRequest
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
            'terminal_type' => ['sometimes', 'string', Rule::enum(TerminalType::class)],
            'is_active' => ['sometimes', 'boolean'],
            'q' => ['sometimes', 'string', 'min:1', 'max:100'],
        ];
    }

    public function page(): PageRequest
    {
        return new PageRequest((int) $this->query('page', 1), (int) $this->query('per_page', PageRequest::DEFAULT_PER_PAGE));
    }
}
