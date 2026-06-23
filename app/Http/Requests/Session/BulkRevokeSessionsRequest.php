<?php

namespace App\Http\Requests\Session;

use Illuminate\Foundation\Http\FormRequest;

class BulkRevokeSessionsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'session_ids'   => 'required|array|min:1',
            'session_ids.*' => 'required|integer|distinct',
        ];
    }
}
