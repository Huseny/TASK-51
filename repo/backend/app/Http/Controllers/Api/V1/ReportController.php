<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Reports\ReportExportRequest;
use App\Http\Requests\Reports\ReportFiltersRequest;
use App\Http\Requests\Reports\ReportTemplateStoreRequest;
use App\Http\Requests\Reports\ReportTemplateUpdateRequest;
use App\Models\ReportExport;
use App\Models\ReportTemplate;
use App\Services\ReportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use ZipArchive;

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
        $directoryId = $this->validateDestination((string) ($payload['directory_id'] ?? $payload['destination'] ?? 'default'));
        $root = $this->approvedExportRoot($directoryId);

        $data = match ($payload['type']) {
            'trends' => $this->reportService->trends($filters),
            'distribution' => $this->reportService->distribution($filters),
            'regions' => ['data' => $this->reportService->regions($filters)],
        };

        $extension = $payload['format'] === 'xlsx' ? 'xlsx' : 'csv';
        $filename = (string) Str::uuid().'.'.$extension;
        $relativeDir = $root['relative_path'];
        $relativePath = $relativeDir.'/'.$filename;
        Storage::disk('local')->makeDirectory($relativeDir);
        $path = Storage::disk('local')->path($relativePath);

        $rows = [];
        if ($payload['type'] === 'regions') {
            $rows[] = ['region', 'total'];
            foreach ($data['data'] as $row) {
                $rows[] = [(string) $row['region'], (string) $row['total']];
            }
        } else {
            $rows[] = ['label', 'value'];
            foreach ($data['labels'] as $index => $label) {
                $value = $data['datasets'][0]['data'][$index] ?? 0;
                $rows[] = [(string) $label, (string) $value];
            }
        }

        if ($extension === 'xlsx') {
            if (! $this->writeXlsx($path, $rows)) {
                return response()->json([
                    'error' => 'internal_server_error',
                    'message' => 'Could not create export file',
                    'details' => (object) [],
                ], 500);
            }
        } else {
            $handle = fopen($path, 'wb');
            if ($handle === false) {
                return response()->json([
                    'error' => 'internal_server_error',
                    'message' => 'Could not create export file',
                    'details' => (object) [],
                ], 500);
            }

            foreach ($rows as $row) {
                fputcsv($handle, $row);
            }

            fclose($handle);
        }

        $export = ReportExport::query()->create([
            'user_id' => $request->user()->id,
            'type' => $payload['type'],
            'format' => $extension,
            'destination' => $directoryId,
            'relative_path' => $relativePath,
            'created_at' => now(),
        ]);

        $url = URL::temporarySignedRoute(
            'reports.exports.download',
            now()->addMinutes(10),
            ['reportExport' => $export->id]
        );

        return response()->json(['url' => $url]);
    }

    public function exportDirectories(): JsonResponse
    {
        $roots = config('reports.export_roots', []);

        $data = collect($roots)
            ->map(fn (array $root, string $id): array => [
                'id' => $id,
                'label' => (string) ($root['label'] ?? $id),
            ])
            ->values();

        return response()->json(['data' => $data]);
    }

    public function download(Request $request, ReportExport $reportExport)
    {
        $user = $request->user();
        if ($user->role !== 'admin' && $reportExport->user_id !== $user->id) {
            return response()->json([
                'error' => 'forbidden',
                'message' => 'You do not have permission to access this export',
            ], 403);
        }

        $path = $reportExport->relative_path;
        if (! Storage::disk('local')->exists($path)) {
            abort(404);
        }

        $filename = basename($path);
        $contentType = str_ends_with($filename, '.xlsx')
            ? 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
            : 'text/csv';

        return response()->download(Storage::disk('local')->path($path), $filename, ['Content-Type' => $contentType]);
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

    private function validateDestination(string $destination): string
    {
        $trimmed = trim($destination);
        if ($trimmed === '') {
            return 'default';
        }

        if (! preg_match('/^[A-Za-z0-9_-]+$/', $trimmed)) {
            throw ValidationException::withMessages([
                'destination' => ['Destination must use only letters, numbers, underscores, or hyphens.'],
            ]);
        }

        return $trimmed;
    }

    /**
     * @return array{label: string, relative_path: string}
     */
    private function approvedExportRoot(string $directoryId): array
    {
        $roots = config('reports.export_roots', []);
        $root = $roots[$directoryId] ?? null;

        if (! is_array($root) || ! isset($root['relative_path'])) {
            throw ValidationException::withMessages([
                'directory_id' => ['Selected export directory is not approved.'],
            ]);
        }

        $relativePath = trim((string) $root['relative_path']);
        if ($relativePath === '' || str_contains($relativePath, '..') || str_starts_with($relativePath, '/')) {
            throw ValidationException::withMessages([
                'directory_id' => ['Selected export directory is not safe.'],
            ]);
        }

        return [
            'label' => (string) ($root['label'] ?? $directoryId),
            'relative_path' => $relativePath,
        ];
    }

    /**
     * @param  array<int, array<int, string>>  $rows
     */
    private function writeXlsx(string $path, array $rows): bool
    {
        $zip = new ZipArchive();
        if ($zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            return false;
        }

        $sheetRows = '';
        foreach ($rows as $rowIndex => $row) {
            $sheetRows .= '<row r="'.($rowIndex + 1).'">';
            foreach ($row as $cellIndex => $value) {
                $column = chr(65 + $cellIndex);
                $sheetRows .= '<c r="'.$column.($rowIndex + 1).'" t="inlineStr"><is><t>'
                    .htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8')
                    .'</t></is></c>';
            }
            $sheetRows .= '</row>';
        }

        $zip->addFromString('[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8"?>\n<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">\n<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>\n<Default Extension="xml" ContentType="application/xml"/>\n<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>\n<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>\n<Override PartName="/docProps/core.xml" ContentType="application/vnd.openxmlformats-package.core-properties+xml"/>\n<Override PartName="/docProps/app.xml" ContentType="application/vnd.openxmlformats-officedocument.extended-properties+xml"/>\n</Types>');
        $zip->addFromString('_rels/.rels', '<?xml version="1.0" encoding="UTF-8"?>\n<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">\n<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>\n<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/package/2006/relationships/metadata/core-properties" Target="docProps/core.xml"/>\n<Relationship Id="rId3" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/extended-properties" Target="docProps/app.xml"/>\n</Relationships>');
        $zip->addFromString('docProps/core.xml', '<?xml version="1.0" encoding="UTF-8"?>\n<cp:coreProperties xmlns:cp="http://schemas.openxmlformats.org/package/2006/metadata/core-properties" xmlns:dc="http://purl.org/dc/elements/1.1/" xmlns:dcterms="http://purl.org/dc/terms/" xmlns:dcmitype="http://purl.org/dc/dcmitype/" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"><dc:title>RoadLink Export</dc:title><dc:creator>RoadLink</dc:creator></cp:coreProperties>');
        $zip->addFromString('docProps/app.xml', '<?xml version="1.0" encoding="UTF-8"?>\n<Properties xmlns="http://schemas.openxmlformats.org/officeDocument/2006/extended-properties" xmlns:vt="http://schemas.openxmlformats.org/officeDocument/2006/docPropsVTypes"><Application>RoadLink</Application></Properties>');
        $zip->addFromString('xl/workbook.xml', '<?xml version="1.0" encoding="UTF-8"?>\n<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets><sheet name="Report" sheetId="1" r:id="rId1"/></sheets></workbook>');
        $zip->addFromString('xl/_rels/workbook.xml.rels', '<?xml version="1.0" encoding="UTF-8"?>\n<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/></Relationships>');
        $zip->addFromString('xl/worksheets/sheet1.xml', '<?xml version="1.0" encoding="UTF-8"?>\n<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>'.$sheetRows.'</sheetData></worksheet>');

        return $zip->close();
    }
}
