<?php

namespace App\Modules\Access\Http\Requests;

use App\Modules\Access\Enums\DevicePlatform;
use Illuminate\Validation\Rule;

/**
 * Mobile sign-in body (CT-04): `{ "username", "password", "platform" }`. The device id and app version come in headers
 * (`EnsureMobileHeaders`). The username is trimmed and lowercased as on the web.
 */
class MobileLoginRequest extends LoginRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            ...parent::rules(),
            'platform' => ['required', Rule::enum(DevicePlatform::class)],
        ];
    }
}
