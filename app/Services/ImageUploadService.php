<?php

namespace App\Services;

use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class ImageUploadService
{
    public const MAX_FILE_SIZE = 10240; // 10MB in KB

    public const MAX_IMAGES = 10;

    public const ALLOWED_TYPES = ['jpg', 'jpeg', 'png', 'webp'];

    /**
     * Validate uploaded image files
     *
     * @param  array  $files  Array of UploadedFile instances
     *
     * @throws ValidationException
     */
    public function validateFiles(array $files): void
    {
        if (count($files) > self::MAX_IMAGES) {
            throw ValidationException::withMessages([
                'images' => ['You can upload a maximum of '.self::MAX_IMAGES.' images.'],
            ]);
        }

        $validator = Validator::make(
            ['images' => $files],
            [
                'images.*' => [
                    'image',
                    'mimes:'.implode(',', self::ALLOWED_TYPES),
                    'max:'.self::MAX_FILE_SIZE,
                ],
            ]
        );

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }
    }
}
