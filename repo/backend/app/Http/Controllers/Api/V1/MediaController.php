<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\MediaAsset;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;

class MediaController extends Controller
{
    public function url(Request $request, MediaAsset $media): JsonResponse
    {
        $user = $request->user();

        $ownsLinkedVehicle = $media->vehicles()
            ->where('owner_id', $user->id)
            ->exists();

        if (! $ownsLinkedVehicle && $user->role !== 'admin') {
            return response()->json([
                'error' => 'forbidden',
                'message' => 'You do not have permission to access this media',
            ], 403);
        }

        $signedUrl = URL::temporarySignedRoute(
            'media.download',
            now()->addMinutes((int) config('media.signed_url_minutes', 10)),
            ['media' => $media->id]
        );

        return response()->json(['url' => $signedUrl]);
    }

    public function download(MediaAsset $media)
    {
        $path = $media->compressed_path ?: $media->disk_path;
        $absolutePath = Storage::disk('local')->path($path);

        if (! is_file($absolutePath)) {
            abort(404);
        }

        return response()->file($absolutePath, ['Content-Type' => $media->mime_type]);
    }
}
