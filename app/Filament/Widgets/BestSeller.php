<?php

namespace App\Filament\Widgets;

use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB; // Import DB facade
use App\Models\Produk; // Import model Produk
use Filament\Tables\Columns\TextColumn;

class BestSeller extends BaseWidget
{
    // Judul widget yang akan ditampilkan di dashboard/halaman
    protected static ?string $heading = 'Produk Best Seller';
    protected static ?int $sort = 3;
    protected int | string | array $columnSpan = 'full';


    // Kolom untuk tabel best seller
    protected function getTableColumns(): array
    {
        return [
            TextColumn::make('nama_produk')
                ->label('Nama Produk')
                ->searchable()
                ->sortable(),
            TextColumn::make('total_terjual')
                ->label('Total Terjual')
                ->numeric()
                ->sortable()
                ->description('Jumlah total kuantitas produk yang terjual.'),

        ];
    }

    // Query untuk mengambil data produk best seller
    public function table(Table $table): Table
    {
        return $table
            ->query(
                Produk::query()
                    ->select('produks.id', 'produks.nama_produk', DB::raw('SUM(detail_penjualans.qty) as total_terjual'))
                    ->join('detail_penjualans', 'produks.id', '=', 'detail_penjualans.produk_id')
                    ->join('penjualans', 'detail_penjualans.penjualan_id', '=', 'penjualans.id')
                    ->where('penjualans.status', 'paid') // Hanya hitung penjualan yang sudah selesai
                    ->groupBy('produks.id', 'produks.nama_produk')
                    ->orderByDesc('total_terjual') // Urutkan dari yang paling banyak terjual
            )
            ->columns($this->getTableColumns()) // Menggunakan kolom yang didefinisikan di atas
            ->filters([
                // Anda bisa menambahkan filter di sini jika ingin memfilter best seller berdasarkan periode waktu, kategori, dll.
                // Contoh:
                // Tables\Filters\DatePickerFilter::make('created_at')
                //     ->label('Tanggal Penjualan')
            ])
            ->actions([
                // Tables\Actions\ViewAction::make(), // Jika Anda ingin menambahkan aksi view per produk
            ])
            ->bulkActions([
                //
            ]);
    }

    // Mengatur apakah widget bisa disegarkan secara otomatis
    protected static ?string $pollingInterval = '10s'; // Refresh setiap 10 detik

    // Mengatur urutan tampilan widget (opsional)
}
