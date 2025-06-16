<?php

namespace App\Filament\Widgets;

use App\Models\Penjualan;
use Carbon\Carbon;
use Filament\Forms\Components\DatePicker;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class StatsOverview extends BaseWidget
{
    use InteractsWithPageFilters;

    protected static ?int $sort = 1;

    protected function getStats(): array
    {
        $startDate = $this->filters['startDate'] ?? null;
        $endDate = $this->filters['endDate'] ?? null;

        // === BASE QUERY ===
        $penjualanQuery = Penjualan::where('status', 'paid');
        $labaQuery = Penjualan::where('status', 'paid');
        $transaksiQuery = Penjualan::where('status', 'paid');

        if ($startDate) {
            $parsedStart = Carbon::parse($startDate);
            $penjualanQuery->where('created_at', '>=', $parsedStart);
            $labaQuery->where('created_at', '>=', $parsedStart);
            $transaksiQuery->where('created_at', '>=', $parsedStart);
        }

        if ($endDate) {
            $parsedEnd = Carbon::parse($endDate);
            $penjualanQuery->where('created_at', '<=', $parsedEnd);
            $labaQuery->where('created_at', '<=', $parsedEnd);
            $transaksiQuery->where('created_at', '<=', $parsedEnd);
        }

        $totalPenjualan = $penjualanQuery->sum('total');
        $totalLaba = $labaQuery->sum('laba');
        $jumlahTransaksi = $transaksiQuery->count();

        // === CHART FUNCTION ===
        $monthlyChart = function ($model, $field = 'total') use ($startDate, $endDate) {
            $query = $model::where('status', 'paid');

            if ($startDate) $query->where('created_at', '>=', Carbon::parse($startDate));
            if ($endDate) $query->where('created_at', '<=', Carbon::parse($endDate));

            $select = $field === 'count' ? 'COUNT(*)' : "SUM($field)";

            $result = $query
                ->selectRaw("MONTH(created_at) as month, $select as total")
                ->groupByRaw("MONTH(created_at)")
                ->pluck('total', 'month')
                ->toArray();

            $data = [];
            for ($i = 1; $i <= 12; $i++) {
                $data[] = (int) ($result[$i] ?? 0);
            }
            return $data;
        };

        // === CHART DATA ===
        $penjualanChart = $monthlyChart(new Penjualan, 'total');
        $labaChart = $monthlyChart(new Penjualan, 'laba');
        $transaksiChart = $monthlyChart(new Penjualan, 'count');

        // === RETURN ===
        return [
            Stat::make('Total Penjualan', 'Rp ' . number_format($totalPenjualan, 0, ',', '.'))
                ->description('Penjualan berhasil dibayar')
                ->descriptionIcon('heroicon-m-arrow-trending-up')
                ->chart($penjualanChart)
                ->color('success'),

            Stat::make('Total Laba', 'Rp ' . number_format($totalLaba, 0, ',', '.'))
                ->description('Keuntungan bersih')
                ->descriptionIcon('heroicon-m-currency-dollar')
                ->chart($labaChart)
                ->color('info'),

            Stat::make('Jumlah Transaksi', number_format($jumlahTransaksi, 0, ',', '.'))
                ->description('Transaksi selesai')
                ->descriptionIcon('heroicon-m-receipt-percent')
                ->chart($transaksiChart)
                ->color('primary'),
        ];
    }
}
