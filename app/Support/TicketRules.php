<?php

namespace App\Support;

class TicketRules
{
    public const CATEGORIES = [
        'dispute',
        'complaint',
        'technical',
        'billing',
        'kyc',
        'loan',
        'settlement',
        'agreement',
        'other',
    ];

    public const PRIORITIES = ['critical', 'high', 'medium', 'low'];

    public const STATUSES = ['open', 'in_progress', 'waiting', 'resolved', 'closed', 'escalated'];

    public const SOURCE_ROLES = ['merchant', 'customer', 'store', 'lender_ops', 'internal'];

    public const DEFAULT_CATEGORY = 'other';

    public const DEFAULT_PRIORITY = 'medium';

    public const DEFAULT_STATUS = 'open';

    public const DEFAULT_SOURCE_ROLE = 'internal';

    public const MAX_ATTACHMENTS = 5;

    public const MAX_ATTACHMENT_KB = 5120; // 5 MB

    public const ALLOWED_EXTENSIONS = ['pdf', 'jpg', 'jpeg', 'png', 'webp'];

    public static function attachmentValidationRule(): string
    {
        return 'file|mimes:' . implode(',', self::ALLOWED_EXTENSIONS) . '|max:' . self::MAX_ATTACHMENT_KB;
    }
}
