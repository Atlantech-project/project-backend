<?php

namespace App\Modules\Access\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Web sign-in body (CT-03): `{ "username", "password" }`. The username is trimmed and lowercased before
 * validation, because the database stores it lowercase (DEC-35, `chk_user_username_lower`).
 */
class LoginRequest extends FormRequest
{
    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'username' => ['required', 'string', 'min:3', 'max:50'],
            'password' => ['required', 'string', 'max:255'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('username'))) {
            $this->merge(['username' => mb_strtolower(trim($this->input('username')))]);
        }
    }
}
