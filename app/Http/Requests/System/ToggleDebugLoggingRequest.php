<?php

namespace App\Http\Requests\System;

use Illuminate\Foundation\Http\FormRequest;

class ToggleDebugLoggingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'enabled' => 'required|boolean',
        ];
    }
}
