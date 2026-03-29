<?php

namespace App\Services;

use App\Jobs\ProcessMediaAsset;
use App\Models\MediaAsset;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleMedia;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class MediaService
{
    /**
     * @return array{media: MediaAsset, deduplicated: bool}
     */
    public function uploadToVehicle(Vehicle $vehicle, UploadedFile $file, User $uploader): array
    {
        return DB::transaction(function () use ($vehicle, $file, $uploader): array {
            $hash = hash_file('sha256', $file->getRealPath());
            $mimeType = (string) $file->getMimeType();
            $originalFilename = $file->getClientOriginalName();
            $originalExtension = strtolower((string) $file->getClientOriginalExtension());
            $normalizedExtension = $this->normalizedExtension($mimeType, $originalExtension);

            $media = MediaAsset::withTrashed()->where('sha256_hash', $hash)->first();
            $deduplicated = $media !== null;

            if (! $media) {
                $diskPath = 'media/'.$hash.'.'.$normalizedExtension;

                Storage::disk('local')->putFileAs('media', $file, $hash.'.'.$normalizedExtension);

                $media = MediaAsset::query()->create([
                    'original_filename' => $originalFilename,
                    'mime_type' => $mimeType,
                    'extension' => $normalizedExtension,
                    'size_bytes' => (int) $file->getSize(),
                    'sha256_hash' => $hash,
                    'disk_path' => $diskPath,
                    'compressed_path' => null,
                    'uploaded_by' => $uploader->id,
                    'created_at' => now(),
                ]);

                ProcessMediaAsset::dispatch($media->id);
            } elseif ($media->trashed()) {
                $media->restore();
            }

            $isImageExtensionMismatch =
                ($mimeType === 'image/png' && in_array($originalExtension, ['jpg', 'jpeg'], true))
                || ($mimeType === 'image/jpeg' && $originalExtension === 'png');

            if ($isImageExtensionMismatch) {
                Log::channel('app')->warning('Media extension and MIME family mismatch tolerated', [
                    'filename' => $originalFilename,
                    'extension' => $originalExtension,
                    'mime_type' => $mimeType,
                ]);
            }

            $nextSort = (int) VehicleMedia::query()
                ->where('vehicle_id', $vehicle->id)
                ->max('sort_order');

            VehicleMedia::query()->updateOrCreate(
                [
                    'vehicle_id' => $vehicle->id,
                    'media_asset_id' => $media->id,
                ],
                [
                    'sort_order' => $nextSort + 1,
                    'is_cover' => false,
                    'created_at' => now(),
                ]
            );

            Log::channel('app')->info(
                sprintf(
                    'Media uploaded: %s (%d bytes, hash: %s, deduplicated: %s)',
                    $originalFilename,
                    (int) $file->getSize(),
                    $hash,
                    $deduplicated ? 'yes' : 'no'
                ),
                [
                    'vehicle_id' => $vehicle->id,
                    'uploaded_by' => $uploader->id,
                ]
            );

            return ['media' => $media->fresh(), 'deduplicated' => $deduplicated];
        });
    }

    private function normalizedExtension(string $mimeType, string $clientExtension): string
    {
        return match ($mimeType) {
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'video/mp4' => 'mp4',
            default => $clientExtension,
        };
    }
}
