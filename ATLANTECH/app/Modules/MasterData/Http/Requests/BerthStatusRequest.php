<?php

namespace App\Modules\MasterData\Http\Requests;

use App\Modules\Shared\Support\VersionToken;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Body with only the berth's `version`: deactivate, activate and maintenance (P2-F2, FR-MD-05).
 */
class BerthStatusRequest extends FormRequest
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
        return ['version' => VersionToken::rules()];
    }
}
