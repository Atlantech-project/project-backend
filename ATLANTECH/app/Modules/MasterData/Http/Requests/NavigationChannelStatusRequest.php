<?php

namespace App\Modules\MasterData\Http\Requests;

use App\Modules\Shared\Support\VersionToken;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Body with only the channel's `version`: deactivate and activate (P2-F1, as terminals and berths in P2-F2).
 */
class NavigationChannelStatusRequest extends FormRequest
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
