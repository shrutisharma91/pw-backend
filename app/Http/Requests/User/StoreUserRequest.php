<?php

namespace App\Http\Requests\User;

use App\Http\Requests\User\Concerns\ValidatesUserAccessFields;
use Illuminate\Foundation\Http\FormRequest;

class StoreUserRequest extends FormRequest
{
    use ValidatesUserAccessFields;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return $this->userAccessFieldRules();
    }

    public function withValidator($validator): void
    {
        $this->validateUserAccessFields($validator);
    }
}
