<?php

namespace App\Modules\Yard\Http\Requests;

use App\Modules\Shared\Support\VersionToken;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Body or query of the yard DELETE endpoints (P2-F4): the version only.
 */
class YardVersionRequest extends FormRequest
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
