<?php

namespace App\Services\Providers;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\ImageManager;

class SpacesService
{
    protected string $disk = 'spaces';
    protected string $cdnUrl;
    protected ImageManager $imageManager;

    public function __construct()
    {
        $this->cdnUrl = rtrim(env('DO_SPACES_CDN_URL', env('DO_SPACES_ENDPOINT')), '/');
        $this->imageManager = new ImageManager(new Driver());
    }

    public function uploadImage(
        UploadedFile $file,
        string $folder = 'Freebyz',
        bool $watermark = false
    ): ?string {
        try {
            $image = $this
                ->imageManager
                ->read($file)
                ->scaleDown(width: 1200);

            // Uncomment after implementing applyWatermark for Intervention v3
            // if ($watermark) {
            //     $image = $this->applyWatermark($image);
            // }

            $encoded = $image->toWebp(quality: 80)->toString();

            $filename = $folder . '/' . Str::uuid() . '.webp';

            Storage::disk($this->disk)->put(
                $filename,
                $encoded,
                'public'
            );

            return $this->cdnUrl . '/' . $filename;
        } catch (\Throwable $e) {
            Log::error('Spaces image upload failed: ' . $e->getMessage());

            return null;
        }
    }

    public function uploadBase64Image(
        ?string $base64,
        string $folder = 'Freebyz',
        bool $watermark = false
    ): ?string {
        if (!$base64) {
            return null;
        }

        $base64 = trim(str_replace(["\n", "\r"], '', $base64));

        if (!preg_match('/^data:image\/(\w+);base64,(.+)$/', $base64, $matches)) {
            return null;
        }

        $data = base64_decode($matches[2]);

        if (!$data) {
            return null;
        }

        try {
            $image = $this
                ->imageManager
                ->read($data)
                ->scaleDown(width: 1200);

            // Uncomment after implementing applyWatermark for Intervention v3
            // if ($watermark) {
            //     $image = $this->applyWatermark($image);
            // }

            $encoded = $image->toWebp(quality: 80)->toString();

            $filename = $folder . '/' . Str::uuid() . '.webp';

            $load = Storage::disk($this->disk)->put(
                $filename,
                $encoded,
                'public'
            );

            Log::info($load);
            return $this->cdnUrl . '/' . $filename;
        } catch (\Throwable $e) {
            Log::error('Spaces base64 upload failed: ' . $e->getMessage());

            return null;
        }
    }

    public function uploadFile(UploadedFile $file, string $folder = 'Freebyz'): ?string
    {
        try {
            $extension = $file->getClientOriginalExtension() ?: 'pdf';
            $filename = $folder . '/' . Str::uuid() . '.' . $extension;

            Storage::disk($this->disk)->put(
                $filename,
                file_get_contents($file->getRealPath()),
                'public'
            );

            return $this->cdnUrl . '/' . $filename;
        } catch (\Throwable $e) {
            Log::error('Spaces file upload failed: ' . $e->getMessage());
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

        return $this->cdnUrl . '/' . ltrim($path, '/');
    }

    public function delete(string $url): bool
    {
        try {
            $path = ltrim(
                str_replace($this->cdnUrl, '', $url),
                '/'
            );

            return Storage::disk($this->disk)->delete($path);
        } catch (\Throwable $e) {
            Log::error('Spaces delete failed: ' . $e->getMessage());

            return false;
        }
    }

    /**
     * Watermark implementation for Intervention Image v3.
     * Uncomment and implement when needed.
     */

    /*
     * protected function applyWatermark($image)
     * {
     *     return $image;
     * }
     */
}
