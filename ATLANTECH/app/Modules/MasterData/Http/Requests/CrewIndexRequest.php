<?php

namespace App\Modules\MasterData\Http\Requests;

use App\Modules\MasterData\Enums\PilotRank;
use App\Modules\Shared\Http\Pagination\PageRequest;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Query string of `GET /api/v1/pilots` and `GET /api/v1/tug-masters` (P2-F3): page, `is_active`, the text search `q`
 * (name or username), and `pilot_rank` for pilots.
 */
class CrewIndexRequest extends FormRequest
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
            'is_active' => ['sometimes', 'boolean'],
            'q' => ['sometimes', 'string', 'min:1', 'max:100'],
            'pilot_rank' => ['sometimes', Rule::enum(PilotRank::class)],
        ];
    }

    public function page(): PageRequest
    {
        return new PageRequest((int) $this->query('page', 1), (int) $this->query('per_page', PageRequest::DEFAULT_PER_PAGE));
    }
}
