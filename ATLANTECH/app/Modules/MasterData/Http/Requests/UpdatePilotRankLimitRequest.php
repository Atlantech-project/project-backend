<?php

namespace App\Modules\MasterData\Http\Requests;

use App\Modules\Shared\Support\VersionToken;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Body of `PATCH /api/v1/pilot-rank-limits/{pilotRankLimit}` (P2-F3, FR-MD-09): the `version` and the largest LOA in
 * metres, or null for no limit (`chk_pilot_rank_limit_loa`). The seniority order is checked by the service (Q9).
 */
class UpdatePilotRankLimitRequest extends FormRequest
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
            'max_vessel_loa' => ['present', 'nullable', 'decimal:0,2', 'gt:0', 'max:99999.99'],
        ];
    }
}
