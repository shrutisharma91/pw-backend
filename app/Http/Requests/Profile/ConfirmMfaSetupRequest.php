<?php

namespace App\Http\Requests\Profile;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates the 6-digit authenticator code used to confirm a pending
 * TOTP reconfiguration initiated via /profile/mfa/setup.
 */
class ConfirmMfaSetupRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'code' => 'required|string|size:6',
        ];
    }

    public function messages(): array
    {
        return [
            'code.size' => 'The authenticator code must be exactly 6 digits.',
        ];
    }
}
