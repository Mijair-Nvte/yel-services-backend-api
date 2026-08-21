<?php

namespace App\Services;

use Aws\S3\S3Client;
use Illuminate\Support\Str;

class R2StorageService
{
    /**
     * Genera una URL pre-firmada dinámica para cualquier disco y módulo
     */
    public function generatePresignedUrl(string $disk, string $basePath, string $fileName, string $mimeType, int $minutes = 15): array
    {
        $extension = pathinfo($fileName, PATHINFO_EXTENSION);
        $safeName = Str::slug(pathinfo($fileName, PATHINFO_FILENAME));
        $shortHash = substr(md5(uniqid()), 0, 8);

        // Ejemplo: vault/user_5/properties/mi-casa_a1b2c3d4.jpg
        $key = trim($basePath, '/') . "/{$safeName}_{$shortHash}.{$extension}";

        $client = new S3Client([
            'version' => 'latest',
            'region' => config("filesystems.disks.{$disk}.region", 'auto'),
            'endpoint' => config("filesystems.disks.{$disk}.endpoint"),
            'credentials' => [
                'key' => config("filesystems.disks.{$disk}.key"),
                'secret' => config("filesystems.disks.{$disk}.secret"),
            ],
            // Usamos el config nativo de Laravel para el path style
            'use_path_style_endpoint' => config("filesystems.disks.{$disk}.use_path_style_endpoint", true), 
        ]);

        $cmd = $client->getCommand('PutObject', [
            'Bucket' => config("filesystems.disks.{$disk}.bucket"),
            'Key' => $key,
            'ContentType' => $mimeType,
        ]);

        $request = $client->createPresignedRequest($cmd, "+{$minutes} minutes");

        return [
            'upload_url' => (string) $request->getUri(),
            'key' => $key,
        ];
    }
}