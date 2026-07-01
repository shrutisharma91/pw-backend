<?php

namespace App\Http\Requests\Profile;

use Illuminate\Foundation\Http\FormRequest;

class UpdateProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * In multipart/form-data requests object fields arrive as JSON strings.
     * Decode them so the array validation rules apply correctly.
     */
    protected function prepareForValidation(): void
    {
        foreach (['notification_preferences', 'notification_channels'] as $field) {
            $value = $this->input($field);

            if (is_string($value) && $value !== '') {
                $decoded = json_decode($value, true);

                if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                    $this->merge([$field => $decoded]);
                }
            }
        }
    }

    public function rules(): array
    {
        $userId = $this->user()->id;

        $imageRules = 'sometimes|image|mimes:jpeg,png,jpg,webp|max:2048';

        return [
            'name'                             => 'sometimes|string|max:100',
            'email'                            => 'sometimes|email|max:255|unique:users,email,' . $userId,
            'mobile'                           => 'sometimes|string|size:10|regex:/^[0-9]{10}$/|unique:users,mobile,' . $userId,
            'profile_image'                    => $imageRules,
            'profile_photo'                    => $imageRules,
            'theme'                            => 'sometimes|in:light,dark',
            'timezone'                         => 'sometimes|timezone:all',
            'notification_preferences'         => 'sometimes|array',
            'notification_preferences.email'   => 'sometimes|boolean',
            'notification_preferences.sms'     => 'sometimes|boolean',
            'notification_preferences.whatsapp'=> 'sometimes|boolean',
            'notification_preferences.in_app'  => 'sometimes|boolean',
            'notification_channels'            => 'sometimes|array',
            'notification_channels.email'      => 'sometimes|boolean',
            'notification_channels.sms'        => 'sometimes|boolean',
            'notification_channels.whatsapp'   => 'sometimes|boolean',
            'notification_channels.in_app'     => 'sometimes|boolean',
        ];
    }

    public function messages(): array
    {
        return [
            'profile_image.image'  => 'The profile image must be a valid image file.',
            'profile_image.mimes'  => 'The profile image must be a file of type: jpg, jpeg, png, webp.',
            'profile_image.max'    => 'The profile image must not be larger than 2 MB.',
            'profile_photo.image'  => 'The profile image must be a valid image file.',
            'profile_photo.mimes'  => 'The profile image must be a file of type: jpg, jpeg, png, webp.',
            'profile_photo.max'    => 'The profile image must not be larger than 2 MB.',
        ];
    }
}
