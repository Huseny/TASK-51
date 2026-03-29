<?php

namespace App\Http\Requests\Vehicles;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class MediaUploadRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'file' => ['required', 'file'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $file = $this->file('file');

            if (! $file) {
                return;
            }

            $extension = strtolower((string) $file->getClientOriginalExtension());
            $mimeType = (string) $file->getMimeType();
            $size = (int) $file->getSize();

            $allowedExtensions = config('media.allowed_extensions', []);
            $allowedMimeTypes = config('media.allowed_mime_types', []);

            if (! in_array($extension, $allowedExtensions, true) || ! in_array($mimeType, $allowedMimeTypes, true)) {
                $validator->errors()->add('file', 'Only JPEG, PNG images (max 8MB) and MP4 videos (max 200MB) are allowed');
                return;
            }

            if (in_array($mimeType, ['image/jpeg', 'image/png'], true) && $size > (int) config('media.max_image_bytes')) {
                $validator->errors()->add('file', 'Only JPEG, PNG images (max 8MB) and MP4 videos (max 200MB) are allowed');
                return;
            }

            if ($mimeType === 'video/mp4' && $size > (int) config('media.max_video_bytes')) {
                $validator->errors()->add('file', 'Only JPEG, PNG images (max 8MB) and MP4 videos (max 200MB) are allowed');
            }
        });
    }
}
