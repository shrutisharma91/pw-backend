<?php

namespace App\Http\Requests\Profile;

use App\Support\PasswordRules;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Validator;

class ChangePasswordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return array_merge(
            [
                'current_password' => 'required|string',
            ],
            PasswordRules::validationRules('new_password')
        );
    }

    public function messages(): array
    {
        return PasswordRules::validationMessages('new_password');
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $user = $this->user();

            if (! Hash::check($this->input('current_password'), $user->password)) {
                $validator->errors()->add('current_password', 'Current password is incorrect.');
            }

            if (Hash::check($this->input('new_password'), $user->password)) {
                $validator->errors()->add('new_password', 'New password must be different from your current password.');
            }
        });
    }
}
