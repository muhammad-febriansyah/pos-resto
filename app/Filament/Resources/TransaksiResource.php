<?php

namespace App\Filament\Resources;

use App\Filament\Resources\TransaksiResource\Pages;
use App\Filament\Resources\TransaksiResource\RelationManagers;
use App\Models\Penjualan;
use App\Models\Transaksi;
use Filament\Forms;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Form;
use Filament\Infolists\Components\Grid;
use Filament\Infolists\Components\Group;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Components\TextEntry\TextEntrySize;
use Filament\Infolists\Infolist;
use Filament\Resources\Resource;
use Filament\Support\Enums\FontWeight;
use Filament\Tables;
use Filament\Tables\Columns\Summarizers\Sum;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class TransaksiResource extends Resource
{
    protected static ?string $model = Penjualan::class;

    protected static ?string $navigationIcon = 'heroicon-o-banknotes';
    protected static ?string $navigationGroup = 'Penjualan';
    protected static ?string $navigationLabel = 'Transaksi';
    protected static ?int $navigationSort = 81;

    public static function getNavigationBadge(): ?string
    {
        return static::getModel()::count();
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        return 'Jumlah Transaksi';
    }


    public static function getGlobalSearchResultTitle(Model $record): string | Htmlable
    {
        return $record->invoice_number;
    }

    public static function getGloballySearchableAttributes(): array
    {
        return ['invoice_number', 'user.name', 'customer.name'];
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
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
            ->poll('10s')
            ->columns([
                TextColumn::make("No")->rowIndex(),
                TextColumn::make('invoice_number')
                    ->searchable()
                    ->sortable()
                    ->label('Nomor Invoice'),
                TextColumn::make('user.name')
                    ->searchable()
                    ->sortable()
                    ->label('Kasir')
                    ->toggleable(isToggledHiddenByDefault: false),
                TextColumn::make('customer.name')
                    ->searchable()
                    ->sortable()
                    ->label('Pelanggan')
                    ->default('Guest') // Menampilkan 'Guest' jika customer_id null
                    ->toggleable(isToggledHiddenByDefault: false),
                TextColumn::make('meja.nama')
                    ->searchable()
                    ->sortable()
                    ->label('Meja')
                    ->default('N/A') // Menampilkan 'N/A' jika meja_id null
                    ->toggleable(isToggledHiddenByDefault: true), // Sembunyikan secara default
                TextColumn::make('type')
                    ->label('Tipe Transaksi')
                    ->colors([
                        'primary' => 'dine_in',
                        'success' => 'take_away',
                        'warning' => 'delivery',
                    ])
                    ->sortable(),
                TextColumn::make('sub_total')
                    ->money('IDR') // Format sebagai mata uang Rupiah
                    ->sortable()
                    ->label('Sub Total'),
                TextColumn::make('ppn')
                    ->suffix('%') // Tampilkan sebagai persentase
                    ->label('PPN (%)')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('biaya_layanan')
                    ->money('IDR') // Format sebagai mata uang Rupiah
                    ->sortable()
                    ->label('Biaya Layanan')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('total')
                    ->money('IDR') // Format sebagai mata uang Rupiah
                    ->sortable()
                    ->label('Total Pembayaran')
                    ->summarize(Sum::make()->label('Total Keseluruhan')), // Total di bagian bawah tabel
                TextColumn::make('laba')
                    ->money('IDR')
                    ->sortable()
                    ->label('Laba')
                    ->summarize(Sum::make()->label('Total Laba')), // Total laba di bagian bawah tabel
                TextColumn::make('payment_method')
                    ->label('Metode Pembayaran')
                    ->badge()
                    ->color(function (Penjualan $record) {
                        return match ($record->payment_method) {
                            'cash' => 'primary',
                            'duitku' => 'success',
                            default => 'secondary',
                        };
                    })
                    ->sortable(),
                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->color(function (Penjualan $record) {
                        return match ($record->status) {
                            'paid' => 'success',
                            'pending' => 'warning',
                            'failed' => 'danger',
                            // Tambahkan warna untuk status lain jika ada
                            default => 'secondary',
                        };
                    })
                    ->sortable(),
                TextColumn::make('created_at')
                    ->dateTime('d M Y, H:i') // Format tanggal dan waktu
                    ->sortable()
                    ->label('Tanggal Transaksi'),
            ])
            ->filters([
                SelectFilter::make('payment_method')
                    ->options([
                        'cash' => 'Tunai',
                        'duitku' => 'Duitku',
                    ])
                    ->label('Metode Pembayaran'),
                SelectFilter::make('type')
                    ->options([
                        'dine_in' => 'Dine In',
                        'take_away' => 'Take Away',
                        'delivery' => 'Delivery',
                    ])
                    ->label('Tipe Transaksi'),
                SelectFilter::make('status')
                    ->options([
                        'paid' => 'Lunas',
                        'pending' => 'Pending',
                        // Tambahkan status lain jika ada, misal 'cancelled'
                    ])
                    ->label('Status Pembayaran'),
                SelectFilter::make('user_id')
                    ->relationship('user', 'name')
                    ->searchable()
                    ->preload()
                    ->label('Kasir'),
                SelectFilter::make('customer_id')
                    ->relationship('customer', 'name')
                    ->searchable()
                    ->preload()
                    ->label('Pelanggan'),
                SelectFilter::make('meja_id')
                    ->relationship('meja', 'nama')
                    ->searchable()
                    ->preload()
                    ->label('Meja'),
                Filter::make('created_at')
                    ->form([
                        DatePicker::make('created_from')->label('Dari Tanggal'),
                        DatePicker::make('created_until')->label('Sampai Tanggal'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['created_from'],
                                fn(Builder $query, $date): Builder => $query->whereDate('created_at', '>=', $date),
                            )
                            ->when(
                                $data['created_until'],
                                fn(Builder $query, $date): Builder => $query->whereDate('created_at', '<=', $date),
                            );
                    })
                    ->label('Rentang Tanggal'),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(), // Tambahkan ViewAction untuk melihat detail
                // Tables\Actions\EditAction::make(),

            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('created_at', 'desc'); // Urutkan berdasarkan tanggal transaksi terbaru
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Grid::make(3) // Grid utama untuk menampung bagian-bagian
                    ->schema([
                        Group::make()
                            ->columnSpan(2) // Mengambil 2 dari 3 kolom
                            ->schema([
                                Section::make('Detail Penjualan')
                                    ->schema([
                                        Grid::make(2)
                                            ->schema([
                                                TextEntry::make('invoice_number')
                                                    ->label('Nomor Invoice')
                                                    ->copyable() // Memungkinkan menyalin nomor invoice
                                                    ->copyMessage('Nomor invoice disalin!')
                                                    ->copyMessageDuration(1500),
                                                TextEntry::make('created_at')
                                                    ->label('Tanggal Transaksi')
                                                    ->dateTime('d M Y, H:i:s'),
                                                TextEntry::make('user.name')
                                                    ->label('Kasir'),
                                                TextEntry::make('status')
                                                    ->label('Status Pembayaran')
                                                    ->badge()
                                                    ->color(fn(string $state): string => match ($state) {
                                                        'paid' => 'success',
                                                        'pending' => 'warning',
                                                        'cancelled' => 'danger',
                                                        default => 'gray',
                                                    }),
                                                TextEntry::make('payment_method')
                                                    ->label('Metode Pembayaran')
                                                    ->badge()
                                                    ->color(fn(string $state): string => match ($state) {
                                                        'cash' => 'success',
                                                        'duitku' => 'info',
                                                        default => 'gray',
                                                    }),
                                                TextEntry::make('type')
                                                    ->label('Tipe Transaksi')
                                                    ->badge()
                                                    ->color(fn(string $state): string => match ($state) {
                                                        'dine_in' => 'primary',
                                                        'take_away' => 'success',
                                                        'delivery' => 'warning',
                                                        default => 'gray',
                                                    }),
                                            ]),
                                    ]),

                                Section::make('Produk Dibeli')
                                    ->schema([
                                        RepeatableEntry::make('details') // Mengacu pada relasi 'details' di model Penjualan
                                            ->label('') // Kosongkan label karena akan ada header kolom di bawah
                                            ->schema([
                                                TextEntry::make('produk.nama_produk') // Mengambil nama produk dari relasi 'produk' di DetailPenjualan
                                                    ->label('Produk')
                                                    ->columnSpan(2),
                                                TextEntry::make('qty')
                                                    ->label('Qty')
                                                    ->numeric(),
                                                TextEntry::make('produk.harga_jual')
                                                    ->label('Harga')
                                                    ->money('IDR'),
                                                TextEntry::make('subtotal_item')
                                                    ->label('Subtotal')
                                                    ->money('IDR'),

                                            ])
                                            // Optional: Sesuaikan layout untuk setiap baris item
                                            ->columns(5) // Misalnya 5 kolom: Produk, Qty, Harga Satuan, Subtotal Item
                                            ->columnSpanFull() // Pastikan RepeatableEntry mengambil seluruh lebar section
                                            ->grid(1), // Ini adalah grid untuk setiap item di dalam repeater
                                    ]),

                            ]),

                        Group::make()
                            ->columnSpan(1) // Mengambil 1 dari 3 kolom
                            ->schema([
                                Section::make('Info Pelanggan & Meja')
                                    ->schema([
                                        TextEntry::make('customer.name')
                                            ->label('Nama Pelanggan')
                                            ->default('Guest'),
                                        TextEntry::make('customer.email')
                                            ->label('Email Pelanggan')
                                            ->default('-'),
                                        TextEntry::make('customer.phone')
                                            ->label('Telepon Pelanggan')
                                            ->default('-'),
                                        TextEntry::make('meja.nama')
                                            ->label('Nama Meja')
                                            ->default('Tanpa Meja'),
                                        TextEntry::make('meja.status')
                                            ->label('Status Meja')
                                            ->default('-')
                                            ->badge()
                                            ->color(fn(string $state): string => match ($state) {
                                                'tersedia' => 'success',
                                                'dipakai' => 'danger',
                                                default => 'gray',
                                            })
                                            ->hidden(fn(?string $state) => !$state || $state === '-'), // Sembunyikan jika tidak ada meja
                                    ]),

                                Section::make('Ringkasan Pembayaran')
                                    ->schema([
                                        TextEntry::make('sub_total')
                                            ->label('Sub Total')
                                            ->money('IDR'),
                                        TextEntry::make('ppn')
                                            ->label('PPN')
                                            ->formatStateUsing(fn($state) => $state . '%'), // Tampilkan sebagai persentase
                                        TextEntry::make('biaya_layanan')
                                            ->label('Biaya Layanan')
                                            ->money('IDR'),
                                        TextEntry::make('total')
                                            ->label('Total Pembayaran')
                                            ->money('IDR')
                                            ->size(TextEntrySize::Large)
                                            ->color('primary')
                                            ->weight(FontWeight::Bold),
                                        TextEntry::make('laba')
                                            ->label('Total Laba')
                                            ->money('IDR')
                                            ->size(TextEntrySize::Medium)
                                            ->color('success')
                                            ->weight(FontWeight::Bold),
                                    ]),

                                // Anda bisa menambahkan aksi di sini jika diperlukan, misal tombol cetak invoice
                                // Actions::make([
                                //     Action::make('cetak_invoice')
                                //         ->label('Cetak Invoice')
                                //         ->icon('heroicon-o-printer')
                                //         ->url(fn (Penjualan $record): string => route('penjualan.print', $record))
                                //         ->openUrlInNewTab(),
                                // ])->alignEnd(),
                            ]),
                    ]),
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
            'index' => Pages\ListTransaksis::route('/'),
            'create' => Pages\CreateTransaksi::route('/create'),
            'edit' => Pages\EditTransaksi::route('/{record}/edit'),
        ];
    }
}
