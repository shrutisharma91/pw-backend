<?php

namespace App\Support;

class DocumentRules
{
    public const DOCUMENT_TYPES = [
        'kyc',
        'agreement',
        'invoice',
        'statement',
        'enach',
        'esign',
        'other',
    ];

    public const ENTITY_TYPES = ['merchant', 'customer', 'store'];

    public const UPLOAD_STATUSES = ['pending_ocr'];

    public const MAX_FILE_KB = 10240; // 10 MB

    public const ALLOWED_EXTENSIONS = ['pdf', 'jpg', 'jpeg', 'png', 'webp'];

    public static function fileValidationRule(): string
    {
        return 'required|file|mimes:' . implode(',', self::ALLOWED_EXTENSIONS) . '|max:' . self::MAX_FILE_KB;
    }
}
