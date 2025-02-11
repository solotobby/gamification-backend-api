<?php
namespace App\Services\Providers;

use Illuminate\Support\Facades\Storage;

class AWSServiceProvider
{

    public function uploadImage($file, $filePath){
        Storage::disk('s3')->put($filePath, file_get_contents($file), 'public');
        return Storage::disk('s3')->url($filePath);

    }
}
