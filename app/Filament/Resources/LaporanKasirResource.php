<?php

namespace App\Filament\Resources;

use App\Filament\Resources\LaporanKasirResource\Pages;
use App\Filament\Resources\LaporanKasirResource\RelationManagers;
use App\Models\LaporanKasir;
use App\Models\Penjualan;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Infolist;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\Summarizers\Sum;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class LaporanKasirResource extends Resource
{
    protected static ?string $model = Penjualan::class;

    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-list';
    protected static ?string $navigationGroup = 'Penjualan';
    protected static ?string $navigationLabel = 'Laporan Kasir';
    protected static ?int $navigationSort = 82;

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                //
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make("No")->rowIndex(),
                TextColumn::make('invoice_number')
                    ->label('Nomor Invoice')
                    ->searchable() // Memungkinkan pencarian berdasarkan nomor invoice
                    ->sortable(), // Memungkinkan pengurutan
                TextColumn::make('user.name') // Menampilkan nama kasir dari relasi user
                    ->label('Kasir')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('customer.name') // Menampilkan nama pelanggan dari relasi customer
                    ->label('Pelanggan')
                    ->searchable()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true), // Sembunyikan secara default
                TextColumn::make('meja.nama') // Menampilkan nama meja dari relasi meja
                    ->label('Meja')
                    ->searchable()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true), // Sembunyikan secara default
                TextColumn::make('payment_method')
                    ->label('Metode Pembayaran')
                    ->badge() // Tampilkan sebagai badge
                    ->color(fn(string $state): string => match ($state) {
                        'cash' => 'success',
                        'duitku' => 'info',
                    }),
                TextColumn::make('type')
                    ->label('Tipe Pesanan')
                    ->badge() // Tampilkan sebagai badge
                    ->colors([
                        'primary' => 'dine_in',
                        'info' => 'take_away',
                        'success' => 'delivery',
                    ]),
                TextColumn::make('sub_total')
                    ->label('Sub Total')
                    ->formatStateUsing(fn($state) => 'Rp ' . number_format($state, 0, ',', '.'))
                    ->summarize(Sum::make()->label('Total Sub Total')->money('IDR')) // Tambah sum
                    ->sortable(),
                TextColumn::make('ppn')
                    ->label('PPN (%)')
                    ->suffix('%') // Tambahkan simbol % di belakang
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('biaya_layanan')
                    ->label('Biaya Layanan')
                    ->formatStateUsing(fn($state) => 'Rp ' . number_format($state, 0, ',', '.'))
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('total')
                    ->label('Total')
                    ->formatStateUsing(fn($state) => 'Rp ' . number_format($state, 0, ',', '.'))
                    ->summarize(Sum::make()->label('Total Keseluruhan')->money('IDR'))
                    ->sortable(),
                TextColumn::make('laba')
                    ->label('Laba')
                    ->formatStateUsing(fn($state) => 'Rp ' . number_format($state, 0, ',', '.'))
                    ->summarize(Sum::make()->label('Total Laba')->money('IDR'))
                    ->sortable(),
                TextColumn::make('status') // Menggunakan BadgeColumn untuk status
                    ->label('Status')
                    ->color(
                        fn(string $state): string => match ($state) {
                            'pending' => 'warning',
                            'paid' => 'success',
                            'cancelled' => 'danger',
                        }
                    )
                    ->badge(),
                TextColumn::make('created_at')
                    ->label('Tanggal Transaksi')
                    ->dateTime('d M Y, H:i') // Format tanggal dan waktu
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Filter Status')
                    ->options([
                        'pending' => 'Pending',
                        'completed' => 'Selesai',
                        'cancelled' => 'Dibatalkan',
                    ]),
                SelectFilter::make('payment_method')
                    ->label('Filter Metode Pembayaran')
                    ->options([
                        'cash' => 'Tunai',
                        'credit' => 'Kredit',
                        'ewallet' => 'E-Wallet',
                        'debit_card' => 'Kartu Debit',
                    ]),
                SelectFilter::make('type')
                    ->label('Filter Tipe Pesanan')
                    ->options([
                        'dine_in' => 'Makan di Tempat',
                        'take_away' => 'Bawa Pulang',
                        'delivery' => 'Pengiriman',
                    ]),
                Filter::make('created_at')
                    ->form([
                        Forms\Components\DatePicker::make('dari_tanggal')
                            ->label('Dari Tanggal'),
                        Forms\Components\DatePicker::make('sampai_tanggal')
                            ->label('Sampai Tanggal'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['dari_tanggal'],
                                fn(Builder $query, $date): Builder => $query->whereDate('created_at', '>=', $date),
                            )
                            ->when(
                                $data['sampai_tanggal'],
                                fn(Builder $query, $date): Builder => $query->whereDate('created_at', '<=', $date),
                            );
                    })
                    ->label('Filter Tanggal Transaksi'),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(), // Aksi untuk melihat detail infolist
                // Tables\Actions\EditAction::make(), // Jika Anda ingin mengizinkan pengeditan
                // Tables\Actions\DeleteAction::make(), // Jika Anda ingin mengizinkan penghapusan
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    // Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    // Definisi Infolist untuk detail penjualan
    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Section::make('Informasi Penjualan') // Bagian informasi utama
                    ->schema([
                        TextEntry::make('invoice_number')->label('Nomor Invoice'),
                        TextEntry::make('status')
                            ->label('Status')
                            ->badge()
                            ->colors([
                                'warning' => 'pending',
                                'success' => 'completed',
                                'danger' => 'cancelled',
                            ]),
                        TextEntry::make('created_at')
                            ->label('Tanggal & Waktu Transaksi')
                            ->dateTime('d M Y, H:i:s'),
                        TextEntry::make('user.name')->label('Kasir'),
                        TextEntry::make('customer.name')
                            ->label('Pelanggan')
                            ->default('Umum'), // Jika tidak ada pelanggan spesifik
                        TextEntry::make('meja.nama')
                            ->label('Meja')
                            ->default('Tidak Ada'), // Jika tidak ada meja
                        TextEntry::make('payment_method')
                            ->label('Metode Pembayaran')
                            ->badge()
                            ->colors([
                                'success' => 'cash',
                                'info' => 'credit',
                                'primary' => 'ewallet',
                                'warning' => 'debit_card',
                            ]),
                        TextEntry::make('type')
                            ->label('Tipe Pesanan')
                            ->badge()
                            ->colors([
                                'primary' => 'dine_in',
                                'info' => 'take_away',
                                'success' => 'delivery',
                            ]),
                    ])->columns(2), // Tampilkan dalam 2 kolom

                Section::make('Rincian Keuangan') // Bagian rincian keuangan
                    ->schema([
                        TextEntry::make('sub_total')
                            ->label('Sub Total')
                            ->formatStateUsing(fn($state) => 'Rp ' . number_format($state, 0, ',', '.')),
                        TextEntry::make('ppn')
                            ->label('PPN (%)')
                            ->suffix('%'),
                        TextEntry::make('biaya_layanan')
                            ->label('Biaya Layanan')
                            ->formatStateUsing(fn($state) => 'Rp ' . number_format($state, 0, ',', '.')),
                        TextEntry::make('total')
                            ->label('Total')
                            ->formatStateUsing(fn($state) => 'Rp ' . number_format($state, 0, ',', '.'))
                            ->size('xl') // Ukuran teks lebih besar
                            ->color('primary'), // Warna teks utama
                        TextEntry::make('laba')
                            ->label('Laba')
                            ->formatStateUsing(fn($state) => 'Rp ' . number_format($state, 0, ',', '.'))
                            ->size('lg')
                            ->color('success'), // Warna teks hijau
                    ])->columns(2),

                Section::make('Detail Lainnya') // Bagian detail tambahan
                    ->schema([
                        TextEntry::make('duitku_reference')
                            ->label('Referensi Duitku')
                            ->default('Tidak ada'),
                        TextEntry::make('updated_at')
                            ->label('Terakhir Diperbarui')
                            ->dateTime('d M Y, H:i:s'),
                    ])->columns(1),
            ]);
    }


    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListLaporanKasirs::route('/'),
            'create' => Pages\CreateLaporanKasir::route('/create'),
            'edit' => Pages\EditLaporanKasir::route('/{record}/edit'),
        ];
    }
}
