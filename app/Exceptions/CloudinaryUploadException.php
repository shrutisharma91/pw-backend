<?php

namespace App\Exceptions;

use Exception;

class CloudinaryUploadException extends Exception
{
    public static function failed(): self
    {
        return new self('Failed to upload profile image. Please try again.');
    }
}
