<?php

namespace App\Jobs;

use App\Models\MediaAsset;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class ProcessMediaAsset implements ShouldQueue
{
    use Queueable;

    public function __construct(private readonly int $mediaAssetId)
    {
    }

    public function handle(): void
    {
        $media = MediaAsset::query()->find($this->mediaAssetId);

        if (! $media) {
            return;
        }

        if ($media->isImage()) {
            $this->compressImage($media);
            return;
        }

        if ($media->isVideo()) {
            $this->compressVideo($media);
        }
    }

    private function compressImage(MediaAsset $media): void
    {
        $absolutePath = Storage::disk('local')->path($media->disk_path);

        if (! is_file($absolutePath)) {
            return;
        }

        $binary = file_get_contents($absolutePath);
        if ($binary === false) {
            return;
        }

        $image = @imagecreatefromstring($binary);
        if (! $image) {
            Log::channel('app')->warning('Unable to process image for compression', ['media_asset_id' => $media->id]);
            return;
        }

        $width = imagesx($image);
        $height = imagesy($image);
        $targetWidth = $width > 1920 ? 1920 : $width;
        $targetHeight = (int) round(($height / max(1, $width)) * $targetWidth);

        $target = imagecreatetruecolor($targetWidth, $targetHeight);
        imagecopyresampled($target, $image, 0, 0, 0, 0, $targetWidth, $targetHeight, $width, $height);

        $compressedRelative = 'media/'.$media->sha256_hash.'_compressed.jpg';
        $compressedAbsolute = Storage::disk('local')->path($compressedRelative);

        imagejpeg($target, $compressedAbsolute, 80);

        imagedestroy($target);
        imagedestroy($image);

        $media->compressed_path = $compressedRelative;
        $media->save();
    }

    private function compressVideo(MediaAsset $media): void
    {
        $absolutePath = Storage::disk('local')->path($media->disk_path);
        $compressedRelative = 'media/'.$media->sha256_hash.'_compressed.mp4';
        $compressedAbsolute = Storage::disk('local')->path($compressedRelative);

        $check = @shell_exec('ffmpeg -version 2>&1');
        if (! is_string($check) || ! str_contains(strtolower($check), 'ffmpeg version')) {
            Log::channel('app')->warning(
                sprintf('FFmpeg not available - skipping video compression for %s', $media->original_filename),
                ['media_asset_id' => $media->id]
            );
            return;
        }

        $command = sprintf(
            'ffmpeg -y -i %s -vf "scale=1280:-2" -c:v libx264 -preset fast -crf 28 -c:a aac -b:a 128k %s 2>&1',
            escapeshellarg($absolutePath),
            escapeshellarg($compressedAbsolute)
        );

        @shell_exec($command);

        if (! is_file($compressedAbsolute)) {
            Log::channel('app')->warning('Video compression failed, original will be used', ['media_asset_id' => $media->id]);
            return;
        }

        $media->compressed_path = $compressedRelative;
        $media->save();
    }
}
