<?php

namespace App\Modules\Shared\Http\Requests;

use App\Modules\Shared\Enums\AuditLogAction;
use App\Modules\Shared\Http\Pagination\PageRequest;
use App\Modules\Shared\Http\Validation\ZonedDateTime;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * The query string of `GET /api/v1/audit-logs` (P2-F8, FR-MD-16): page, filters and the actor text search.
 */
class AuditLogIndexRequest extends FormRequest
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
            'entity_type' => ['sometimes', 'string', 'max:60'],
            'entity_id' => ['sometimes', 'integer', 'min:1'],
            'actor_id' => ['sometimes', 'integer', 'min:1'],
            'action' => ['sometimes', 'string', Rule::enum(AuditLogAction::class)],
            'from' => ['sometimes', ...ZonedDateTime::rules(), Rule::when($this->has('to'), ['before_or_equal:to'])],
            'to' => ['sometimes', ...ZonedDateTime::rules()],
            'q' => ['sometimes', 'string', 'min:1', 'max:100'],
        ];
    }

    public function page(): PageRequest
    {
        return new PageRequest((int) $this->query('page', 1), (int) $this->query('per_page', PageRequest::DEFAULT_PER_PAGE));
    }
}
