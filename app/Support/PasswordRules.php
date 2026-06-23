<?php

namespace App\Support;

class PasswordRules
{
    public static function validationRules(string $field = 'password'): array
    {
        return [
            $field              => [
                'required',
                'string',
                'min:8',
                'confirmed',
                'regex:/[A-Z]/',
                'regex:/[0-9]/',
                'regex:/[@$!%*?&]/',
            ],
            "{$field}_confirmation" => 'required|string',
        ];
    }

    public static function validationMessages(string $field = 'password'): array
    {
        return [
            "{$field}.regex" => 'Password must include uppercase, number, and special character (@$!%*?&).',
        ];
    }
}
