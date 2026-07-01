<?php

namespace App\Services;

use App\Exceptions\CloudinaryUploadException;
use CloudinaryLabs\CloudinaryLaravel\Facades\Cloudinary;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Throwable;

class CloudinaryService
{
    private const PROFILE_FOLDER = 'profile-images';

    /**
     * @return array{secure_url: string, public_id: string}
     */
    public function uploadProfileImage(UploadedFile $file): array
    {
        try {
            $result = Cloudinary::uploadApi()->upload(
                $file->getRealPath(),
                [
                    'folder'         => self::PROFILE_FOLDER,
                    'quality'        => 'auto',
                    'fetch_format'   => 'auto',
                    'transformation' => [
                        ['width' => 300, 'height' => 300, 'crop' => 'fill'],
                    ],
                ]
            );

            return [
                'secure_url' => $result['secure_url'],
                'public_id'  => $result['public_id'],
            ];
        } catch (Throwable $e) {
            Log::error('Cloudinary profile image upload failed', [
                'error'   => $e->getMessage(),
                'user_id' => auth()->id(),
            ]);

            throw CloudinaryUploadException::failed();
        }
    }

    public function deleteByPublicId(?string $publicId): void
    {
        if (empty($publicId)) {
            return;
        }

        try {
            Cloudinary::uploadApi()->destroy($publicId);
        } catch (Throwable $e) {
            Log::warning('Cloudinary profile image deletion failed', [
                'public_id' => $publicId,
                'error'     => $e->getMessage(),
                'user_id'   => auth()->id(),
            ]);
        }
    }
}
