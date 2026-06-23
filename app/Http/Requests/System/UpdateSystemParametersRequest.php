<?php

namespace App\Http\Requests\System;

use App\Support\SystemParameterSchema;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdateSystemParametersRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'parameters'         => 'required|array|min:1',
            'parameters.*.key'   => 'required|string',
            'parameters.*.value' => 'required',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $unknownKeys = collect($this->input('parameters', []))
                ->pluck('key')
                ->filter(fn ($key) => ! SystemParameterSchema::has($key));

            if ($unknownKeys->isNotEmpty()) {
                $validator->errors()->add(
                    'parameters',
                    'Unknown parameter keys: ' . $unknownKeys->implode(', ')
                );
            }
        });
    }
}
