<?php

namespace App\Modules\Access\Http\Requests;

use App\Modules\Access\Services\SignInService;
use App\Modules\Shared\Errors\ErrorCode;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

/**
 * Password change body (CT-03): `{ "current_password", "new_password", "new_password_confirmation" }`.
 * The new password has at least 8 characters with letters and numbers, and differs from the current one (P1-C2).
 * A wrong current password is audited as REJECTED_ATTEMPT, since it can mean someone is guessing from an open session.
 */
class ChangePasswordRequest extends FormRequest
{
    /**
     * @return array<string, array<int, string|ValidationRule>>
     */
    public function rules(): array
    {
        return [
            'current_password' => ['required', 'string', 'current_password:web'],
            'new_password' => ['required', 'string', 'max:255', 'confirmed', 'different:current_password', Password::min(8)->letters()->numbers()],
        ];
    }

    protected function failedValidation(Validator $validator): void
    {
        if ($validator->errors()->has('current_password') && $this->user() !== null) {
            app(SignInService::class)->recordRejectedAttempt(
                $this->method().' /'.$this->path(),
                ErrorCode::ValidationFailed,
                'wrong_current_password',
                $this->user()->username,
                $this->user(),
            );
        }

        parent::failedValidation($validator);
    }
}
