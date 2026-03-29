<?php

namespace App\Services;

use App\Models\RideOrder;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class ReportService
{
    /**
     * @param  array<string, mixed>  $filters
     * @return array{labels: array<int, string>, datasets: array<int, array<string, mixed>>}
     */
    public function trends(array $filters): array
    {
        $grouping = (string) ($filters['grouping'] ?? 'day');
        $dateExpression = $grouping === 'month'
            ? "DATE_FORMAT(created_at, '%Y-%m')"
            : 'DATE(created_at)';

        $rows = $this->baseRideQuery($filters)
            ->selectRaw($dateExpression.' as period, COUNT(*) as total')
            ->groupBy('period')
            ->orderBy('period')
            ->get();

        return [
            'labels' => $rows->pluck('period')->map(fn ($period) => (string) $period)->all(),
            'datasets' => [[
                'label' => 'Rides',
                'data' => $rows->pluck('total')->map(fn ($total) => (int) $total)->all(),
            ]],
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array{labels: array<int, string>, datasets: array<int, array<string, mixed>>}
     */
    public function distribution(array $filters): array
    {
        $rows = $this->baseRideQuery($filters)
            ->select('status', DB::raw('COUNT(*) as total'))
            ->groupBy('status')
            ->orderBy('status')
            ->get();

        return [
            'labels' => $rows->pluck('status')->map(fn ($status) => (string) $status)->all(),
            'datasets' => [[
                'label' => 'Ride Status',
                'data' => $rows->pluck('total')->map(fn ($total) => (int) $total)->all(),
            ]],
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<int, array{region: string, total: int}>
     */
    public function regions(array $filters): array
    {
        $regionRules = $this->regionKeywords();

        $rows = $this->baseRideQuery($filters)
            ->select('origin_address')
            ->get();

        $counts = [];
        foreach ($rows as $row) {
            $region = $this->mapRegion((string) $row->origin_address, $regionRules);
            $counts[$region] = ($counts[$region] ?? 0) + 1;
        }

        ksort($counts);

        $result = [];
        foreach ($counts as $region => $count) {
            $result[] = [
                'region' => (string) $region,
                'total' => (int) $count,
            ];
        }

        return $result;
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function baseRideQuery(array $filters): Builder
    {
        $query = RideOrder::query();

        if (! empty($filters['start_date'])) {
            $query->whereDate('created_at', '>=', (string) $filters['start_date']);
        }

        if (! empty($filters['end_date'])) {
            $query->whereDate('created_at', '<=', (string) $filters['end_date']);
        }

        return $query;
    }

    /**
     * @return array<string, array<int, string>>
     */
    private function regionKeywords(): array
    {
        $file = database_path('data/regions.json');

        if (! is_file($file)) {
            return [];
        }

        $decoded = json_decode((string) file_get_contents($file), true);

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * @param  array<string, array<int, string>>  $regionRules
     */
    private function mapRegion(string $address, array $regionRules): string
    {
        $haystack = mb_strtolower($address);

        foreach ($regionRules as $region => $keywords) {
            foreach ($keywords as $keyword) {
                if (mb_strpos($haystack, mb_strtolower($keyword)) !== false) {
                    return (string) $region;
                }
            }
        }

        return 'Other';
    }
}
