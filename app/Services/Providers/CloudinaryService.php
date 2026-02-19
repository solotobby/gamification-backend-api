<?php

namespace App\Services\Providers;

use CloudinaryLabs\CloudinaryLaravel\Facades\Cloudinary;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Http\UploadedFile;

class CloudinaryService
{
    public function uploadImage($file, string $folder = 'uploads'): ?string
    {
        if (!$file instanceof UploadedFile) {
            Log::error('Invalid file passed to uploadImage', ['file' => $file]);
            return null;
        }

        try {
            $uploadedFile = Cloudinary::upload(
                $file->getRealPath(),
                [
                    'folder' => $folder,
                    'resource_type' => 'image',
                    'transformation' => [
                        'quality' => 'auto:good',
                        'fetch_format' => 'auto',
                    ],
                ]
            );

            return $uploadedFile->getSecurePath();
        } catch (\Exception $e) {
            Log::error('Cloudinary image upload failed: ' . $e->getMessage());
            return null;
        }
    }
    public function uploadBase64Image(?string $base64, string $folder = 'uploads'): ?string
    {
        if (!$base64) {
            return null;
        }

        // remove spaces/newlines
        $base64 = trim($base64);
        $base64 = str_replace(["\n", "\r"], '', $base64);

        // validate base64 structure
        if (!preg_match('/^data:image\/(\w+);base64,/', $base64)) {
            return null;
        }

        try {
            $uploadedFile = Cloudinary::upload($base64, [
                'folder' => $folder,
                'resource_type' => 'image',
                'transformation' => [
                    'quality' => 'auto:good',
                    'fetch_format' => 'auto',
                ],
            ]);

            return $uploadedFile?->getSecurePath();
        } catch (\Throwable $e) {
            Log::error('Cloudinary base64 upload failed', [
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }


    public function uploadFile($file, string $folder = 'files'): ?string
    {
        try {
            $uploadedFile = Cloudinary::uploadFile(
                $file->getRealPath(),
                [
                    'folder' => $folder,
                    'resource_type' => 'raw',
                ]
            );

            $publicId = $uploadedFile->getPublicId();
            $cloudName = config('cloudinary.cloud_name');

            return "https://res.cloudinary.com/{$cloudName}/raw/upload/{$publicId}";
        } catch (\Exception $e) {
            Log::error('Cloudinary file upload failed: ' . $e->getMessage());
            return null;
        }
    }

    public function displayImage(?string $path): string
    {
        if (!$path) {
            return '';
        }

        if (Str::startsWith($path, ['http://', 'https://'])) {
            return $path;
        }

        if (Storage::disk('public')->exists($path)) {
            return Storage::url($path);
        }

        if (file_exists(public_path($path))) {
            return asset($path);
        }

        return '';
    }
}
