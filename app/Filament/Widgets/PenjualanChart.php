<?php

namespace App\Filament\Widgets;

use App\Models\Penjualan;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Leandrocfe\FilamentApexCharts\Widgets\ApexChartWidget;
use Filament\Widgets\Concerns\InteractsWithPageFilters;

class PenjualanChart extends ApexChartWidget
{
    use InteractsWithPageFilters;

    protected static ?string $chartId = 'penjualanChart';
    protected static ?string $heading = 'Grafik Penjualan Bulanan';
    protected static ?int $sort = 2;
    protected int | string | array $columnSpan = 'full';
    protected static ?int $contentHeight = 500; //px


    protected function getOptions(): array
    {
        $startDate = $this->filters['startDate'] ?? null;
        $endDate = $this->filters['endDate'] ?? null;

        $query = Penjualan::query()
            ->select(
                DB::raw('YEAR(created_at) as year'),
                DB::raw('MONTH(created_at) as month'),
                DB::raw('SUM(total) as total_penjualan')
            )
            ->where('status', 'paid');

        if ($startDate) {
            $query->whereDate('created_at', '>=', Carbon::parse($startDate));
        }

        if ($endDate) {
            $query->whereDate('created_at', '<=', Carbon::parse($endDate));
        }

        $data = $query
            ->groupBy('year', 'month')
            ->orderBy('year')
            ->orderBy('month')
            ->get();

        $labels = [];
        $seriesData = [];

        // Siapkan array data berdasarkan hasil query
        $monthlySales = [];
        foreach ($data as $item) {
            $monthName = Carbon::create(null, $item->month, 1)->translatedFormat('M Y');
            $monthlySales[$monthName] = $item->total_penjualan;
        }

        // Ambil data 12 bulan terakhir
        $currentMonth = Carbon::now();
        for ($i = 11; $i >= 0; $i--) {
            $date = $currentMonth->copy()->subMonths($i);
            $monthName = $date->translatedFormat('M Y');
            $labels[] = $monthName;
            $seriesData[] = $monthlySales[$monthName] ?? 0;
        }

        return [
            'chart' => [
                'type' => 'bar',
                'height' => 450,
            ],
            'series' => [
                [
                    'name' => 'Total Penjualan',
                    'data' => $seriesData,
                ],
            ],
            'xaxis' => [
                'categories' => $labels,
                'labels' => [
                    'style' => [
                        'fontFamily' => 'inherit',
                        'fontWeight' => 600,
                    ],
                ],
            ],
            'yaxis' => [
                'labels' => [
                    'style' => [
                        'fontFamily' => 'inherit',
                    ],
                ],
            ],
            'colors' => ['#f59e0b'],
            'plotOptions' => [
                'bar' => [
                    'borderRadius' => 5,
                    'horizontal' => false,
                ],
            ],
            'dataLabels' => [
                'enabled' => false,
            ],
            'tooltip' => [
                'y' => [
                    // Ini adalah kode JavaScript yang akan dieksekusi di browser oleh ApexCharts.
                    // Fungsi ini memformat nilai (val) menjadi mata uang Rupiah.
                    'formatter' => 'function (val) {
                        return "Rp " + val.toLocaleString("id-ID");
                    }',
                ],
            ],
        ];
    }
}
