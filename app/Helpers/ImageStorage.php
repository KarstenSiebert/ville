<?php

namespace App\Helpers;

class ImageStorage
{
    public static function saveBase64Image(string $base64, string $preferredName = null): ?string
    {       
        $storagePath = storage_path('app/public/logos/');

        $publicUrl   = '/storage/logos/';

        if (!is_dir($storagePath)) {
            mkdir($storagePath, 0755, true);
        }
        
        if (str_contains($base64, ',')) {
            $base64 = explode(',', $base64)[1];
        }

        $binary = base64_decode($base64, true);

        if ($binary === false) {
            return null;
        }

        $finfo = finfo_open(FILEINFO_MIME_TYPE);

        $mime  = finfo_buffer($finfo, $binary);

        finfo_close($finfo);

        $ext = match ($mime) {
            'image/jpeg' => 'jpg',
            'image/png'  => 'png',
            'image/webp' => 'webp',
            default      => null,
        };

        if (!$ext) {
            return null;
        }

        $filename = $preferredName
            ? preg_replace('/[^a-zA-Z0-9_\-]/', '', $preferredName)
            : null;

        if ($filename) {
            $filename .= substr(sha1($binary), 0, 4);
        
        } else {
            $filename = substr(sha1($binary), 0, 20);
        }

        $filePath = $storagePath . $filename . '.' . $ext;

        if (!file_exists($filePath)) {
            file_put_contents($filePath, $binary);
        }

        return $publicUrl . $filename . '.' . $ext;
    }

}
