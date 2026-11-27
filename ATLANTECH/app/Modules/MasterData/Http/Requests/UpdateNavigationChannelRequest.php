<?php

namespace App\Modules\MasterData\Http\Requests;

use App\Modules\MasterData\Models\NavigationChannel;
use App\Modules\Shared\Support\VersionToken;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Body of `PUT /api/v1/navigation-channels/{channel}` (P2-F1): every field optional (a field that is not sent keeps its
 * value), with the channel's `version`. A channel is never deleted; `is_active` turns it off or on.
 */
class UpdateNavigationChannelRequest extends FormRequest
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
        $channel = $this->route('channel');

        return [
            'name' => ['sometimes', 'required', 'string', 'max:100', Rule::unique('navigation_channels', 'name')->ignore($channel instanceof NavigationChannel ? $channel->id : null)],
            ...NavigationChannelRules::measurements(required: false),
            'is_active' => ['sometimes', 'required', 'boolean'],
            'version' => VersionToken::rules(),
        ];
    }
}
