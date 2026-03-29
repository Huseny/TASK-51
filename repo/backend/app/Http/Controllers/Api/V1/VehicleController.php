<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Vehicles\MediaUploadRequest;
use App\Http\Requests\Vehicles\VehicleMediaReorderRequest;
use App\Http\Requests\Vehicles\VehicleStoreRequest;
use App\Http\Requests\Vehicles\VehicleUpdateRequest;
use App\Models\MediaAsset;
use App\Models\Vehicle;
use App\Models\VehicleMedia;
use App\Services\MediaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;

class VehicleController extends Controller
{
    public function __construct(private readonly MediaService $mediaService)
    {
    }

    public function store(VehicleStoreRequest $request): JsonResponse
    {
        $vehicle = Vehicle::query()->create([
            ...$request->validated(),
            'owner_id' => $request->user()->id,
        ]);

        return response()->json(['vehicle' => $vehicle], 201);
    }

    public function index(Request $request): JsonResponse
    {
        $query = Vehicle::query()->with(['mediaAssets'])->orderByDesc('created_at');

        if ($request->user()->role !== 'admin') {
            $query->where('owner_id', $request->user()->id);
        }

        return response()->json(['data' => $query->get()]);
    }

    public function show(Request $request, Vehicle $vehicle): JsonResponse
    {
        if (! $this->canManageVehicle($request->user()->id, $request->user()->role, $vehicle)) {
            return response()->json([
                'error' => 'forbidden',
                'message' => 'You do not have permission to access this vehicle',
            ], 403);
        }

        $vehicle->load(['mediaAssets']);

        return response()->json([
            'vehicle' => $vehicle,
        ]);
    }

    public function update(VehicleUpdateRequest $request, Vehicle $vehicle): JsonResponse
    {
        if (! $this->canManageVehicle($request->user()->id, $request->user()->role, $vehicle)) {
            return response()->json(['error' => 'forbidden', 'message' => 'You do not have permission to update this vehicle'], 403);
        }

        $vehicle->fill($request->validated());
        $vehicle->save();

        return response()->json(['vehicle' => $vehicle]);
    }

    public function destroy(Request $request, Vehicle $vehicle): JsonResponse
    {
        if (! $this->canManageVehicle($request->user()->id, $request->user()->role, $vehicle)) {
            return response()->json(['error' => 'forbidden', 'message' => 'You do not have permission to delete this vehicle'], 403);
        }

        $vehicle->delete();

        return response()->json(['message' => 'Vehicle deleted']);
    }

    public function uploadMedia(MediaUploadRequest $request, Vehicle $vehicle): JsonResponse
    {
        if (! $this->canManageVehicle($request->user()->id, $request->user()->role, $vehicle)) {
            return response()->json(['error' => 'forbidden', 'message' => 'You do not have permission to upload to this vehicle'], 403);
        }

        $result = $this->mediaService->uploadToVehicle($vehicle, $request->file('file'), $request->user());
        $media = $result['media'];

        $signedUrl = URL::temporarySignedRoute(
            'media.download',
            now()->addMinutes((int) config('media.signed_url_minutes', 10)),
            ['media' => $media->id]
        );

        return response()->json([
            'media' => $media,
            'deduplicated' => $result['deduplicated'],
            'url' => $signedUrl,
        ], 201);
    }

    public function reorderMedia(VehicleMediaReorderRequest $request, Vehicle $vehicle): JsonResponse
    {
        if (! $this->canManageVehicle($request->user()->id, $request->user()->role, $vehicle)) {
            return response()->json(['error' => 'forbidden', 'message' => 'You do not have permission to reorder this gallery'], 403);
        }

        $payload = $request->validated('order');
        $mediaIds = collect($payload)->pluck('media_id')->values();

        $count = VehicleMedia::query()
            ->where('vehicle_id', $vehicle->id)
            ->whereIn('media_asset_id', $mediaIds)
            ->count();

        if ($count !== $mediaIds->count()) {
            return response()->json([
                'error' => 'validation_error',
                'message' => 'One or more media assets do not belong to this vehicle',
                'details' => ['order' => ['Invalid media IDs for this vehicle']],
            ], 422);
        }

        DB::transaction(function () use ($vehicle, $payload): void {
            foreach ($payload as $item) {
                VehicleMedia::query()
                    ->where('vehicle_id', $vehicle->id)
                    ->where('media_asset_id', $item['media_id'])
                    ->update(['sort_order' => $item['sort_order']]);
            }
        });

        return response()->json(['message' => 'Gallery reordered']);
    }

    public function setCover(Request $request, Vehicle $vehicle, int $mediaId): JsonResponse
    {
        if (! $this->canManageVehicle($request->user()->id, $request->user()->role, $vehicle)) {
            return response()->json(['error' => 'forbidden', 'message' => 'You do not have permission to update this gallery'], 403);
        }

        $pivot = VehicleMedia::query()
            ->where('vehicle_id', $vehicle->id)
            ->where('media_asset_id', $mediaId)
            ->first();

        if (! $pivot) {
            return response()->json(['error' => 'validation_error', 'message' => 'Media does not belong to this vehicle', 'details' => (object) []], 422);
        }

        $media = MediaAsset::query()->findOrFail($mediaId);
        if (! $media->isImage()) {
            return response()->json(['error' => 'validation_error', 'message' => 'Only images can be cover media', 'details' => (object) []], 422);
        }

        DB::transaction(function () use ($vehicle, $mediaId): void {
            VehicleMedia::query()->where('vehicle_id', $vehicle->id)->update(['is_cover' => false]);

            VehicleMedia::query()
                ->where('vehicle_id', $vehicle->id)
                ->where('media_asset_id', $mediaId)
                ->update(['is_cover' => true]);
        });

        return response()->json(['message' => 'Cover updated']);
    }

    public function removeMedia(Request $request, Vehicle $vehicle, int $mediaId): JsonResponse
    {
        if (! $this->canManageVehicle($request->user()->id, $request->user()->role, $vehicle)) {
            return response()->json(['error' => 'forbidden', 'message' => 'You do not have permission to update this gallery'], 403);
        }

        VehicleMedia::query()
            ->where('vehicle_id', $vehicle->id)
            ->where('media_asset_id', $mediaId)
            ->delete();

        return response()->json(['message' => 'Media removed from gallery']);
    }

    private function canManageVehicle(int $userId, string $role, Vehicle $vehicle): bool
    {
        return $role === 'admin' || $vehicle->owner_id === $userId;
    }
}
