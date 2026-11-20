<?php

namespace App\Modules\Access\Http\Requests;

use App\Modules\Shared\Support\VersionToken;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Body with only the user's `version`: deactivate, activate and end a role (P2-E1, P2-E2).
 */
class UserVersionRequest extends FormRequest
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
