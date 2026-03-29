<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Reports\ReportExportRequest;
use App\Http\Requests\Reports\ReportFiltersRequest;
use App\Http\Requests\Reports\ReportTemplateStoreRequest;
use App\Http\Requests\Reports\ReportTemplateUpdateRequest;
use App\Models\ReportTemplate;
use App\Services\ReportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

class ReportController extends Controller
{
    public function __construct(private readonly ReportService $reportService)
    {
    }

    public function trends(ReportFiltersRequest $request): JsonResponse
    {
        return response()->json($this->reportService->trends($request->validated()));
    }

    public function distribution(ReportFiltersRequest $request): JsonResponse
    {
        return response()->json($this->reportService->distribution($request->validated()));
    }

    public function regions(ReportFiltersRequest $request): JsonResponse
    {
        return response()->json(['data' => $this->reportService->regions($request->validated())]);
    }

    public function export(ReportExportRequest $request): JsonResponse
    {
        $payload = $request->validated();
        $filters = $payload['filters'] ?? [];

        $data = match ($payload['type']) {
            'trends' => $this->reportService->trends($filters),
            'distribution' => $this->reportService->distribution($filters),
            'regions' => ['data' => $this->reportService->regions($filters)],
        };

        $filename = (string) Str::uuid().'.csv';
        Storage::disk('local')->makeDirectory('exports');
        $path = Storage::disk('local')->path('exports/'.$filename);

        $handle = fopen($path, 'wb');
        if ($handle === false) {
            return response()->json([
                'error' => 'internal_server_error',
                'message' => 'Could not create export file',
                'details' => (object) [],
            ], 500);
        }

        if ($payload['type'] === 'regions') {
            fputcsv($handle, ['region', 'total']);
            foreach ($data['data'] as $row) {
                fputcsv($handle, [$row['region'], $row['total']]);
            }
        } else {
            fputcsv($handle, ['label', 'value']);
            foreach ($data['labels'] as $index => $label) {
                $value = $data['datasets'][0]['data'][$index] ?? 0;
                fputcsv($handle, [$label, $value]);
            }
        }

        fclose($handle);

        $url = URL::temporarySignedRoute(
            'reports.exports.download',
            now()->addMinutes(10),
            ['filename' => $filename]
        );

        return response()->json(['url' => $url]);
    }

    public function download(string $filename)
    {
        $path = 'exports/'.$filename;
        if (! Storage::disk('local')->exists($path)) {
            abort(404);
        }

        return response()->download(Storage::disk('local')->path($path), $filename, ['Content-Type' => 'text/csv']);
    }

    public function templates(Request $request): JsonResponse
    {
        $templates = ReportTemplate::query()
            ->where('user_id', $request->user()->id)
            ->orderByDesc('created_at')
            ->get();

        return response()->json(['data' => $templates]);
    }

    public function storeTemplate(ReportTemplateStoreRequest $request): JsonResponse
    {
        $payload = $request->validated();

        $template = ReportTemplate::query()->create([
            'name' => $payload['name'],
            'user_id' => $request->user()->id,
            'config_json' => $payload['config'],
            'created_at' => now(),
        ]);

        return response()->json(['template' => $template], 201);
    }

    public function updateTemplate(ReportTemplateUpdateRequest $request, ReportTemplate $template): JsonResponse
    {
        if ($template->user_id !== $request->user()->id) {
            return response()->json([
                'error' => 'forbidden',
                'message' => 'You do not have permission to edit this template',
            ], 403);
        }

        $payload = $request->validated();

        $template->fill([
            'name' => $payload['name'] ?? $template->name,
            'config_json' => $payload['config'] ?? $template->config_json,
        ])->save();

        return response()->json(['template' => $template]);
    }

    public function destroyTemplate(Request $request, ReportTemplate $template): JsonResponse
    {
        if ($template->user_id !== $request->user()->id) {
            return response()->json([
                'error' => 'forbidden',
                'message' => 'You do not have permission to delete this template',
            ], 403);
        }

        $template->delete();

        return response()->json(['message' => 'Template deleted']);
    }
}
