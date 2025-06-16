<?php

namespace App\Filament\Resources\BestSellerResource\Pages;

use App\Filament\Resources\BestSellerResource;
use App\Models\Produk;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class ListBestSellers extends ListRecords
{
    protected static string $resource = BestSellerResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }

    protected function getTableQuery(): Builder
    {
        return \App\Models\Produk::query()
            ->select('produks.id', 'produks.nama_produk', DB::raw('SUM(detail_penjualans.qty) as total_terjual'))
            ->join('detail_penjualans', 'produks.id', '=', 'detail_penjualans.produk_id')
            ->join('penjualans', 'detail_penjualans.penjualan_id', '=', 'penjualans.id')
            ->where('penjualans.status', 'paid')
            ->groupBy('produks.id', 'produks.nama_produk')
            ->orderByDesc('total_terjual');
    }
}
