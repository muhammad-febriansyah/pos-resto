<?php

namespace App\Filament\Resources;

use App\Filament\Resources\HutangResource\Pages;
use App\Filament\Resources\HutangResource\RelationManagers;
use App\Filament\Resources\HutangResource\RelationManagers\BayarHutangRelationManager;
use App\Filament\Resources\HutangResource\RelationManagers\BayarHutangsRelationManagerRelationManager;
use App\Models\Hutang;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Fieldset;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Infolists\Components\Group;
use Filament\Infolists\Components\ImageEntry;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\Section as ComponentsSection;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Infolist;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Actions\Action;
use Filament\Tables\Actions\BulkActionGroup;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\DeleteBulkAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Facades\Storage;

class HutangResource extends Resource
{
    protected static ?string $model = Hutang::class;

    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-list';
    protected static ?string $navigationGroup = 'Pencatatan';
    protected static ?string $navigationLabel = 'Daftar Hutang';
    protected static ?int $navigationSort = 11;




    // Metode untuk mendefinisikan tabel daftar Hutang
    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Informasi Detail Hutang')
                    ->description('Isi detail informasi hutang di bawah ini.')
                    ->columns(2)
                    ->schema([
                        Fieldset::make('Informasi Dasar')
                            ->columns(1)
                            ->schema([
                                Select::make('user_id')
                                    ->options(User::where('role', '=', 'customer')->get()->pluck('name', 'id'))
                                    ->label('Customer')
                                    ->required()
                                    ->searchable()
                                    ->preload()
                                    ->placeholder('Pilih pengguna terkait hutang ini')
                                    ->helperText('Pilih siapa yang memiliki hutang ini.'),
                                TextInput::make('jml')
                                    ->label('Jumlah Hutang')
                                    ->numeric()
                                    ->default(0)
                                    ->required()
                                    ->prefix('Rp')
                                    ->maxValue(999999999999999)
                                    ->placeholder('Contoh: 1.500.000') // Placeholder dengan format
                                    ->helperText('Masukkan jumlah total hutang dalam Rupiah. Angka akan diformat otomatis saat diketik.')
                                    ->live() // Penting untuk memicu Livewire agar JavaScript bisa bekerja
                                    // Mengformat nilai saat form dimuat atau diperbarui oleh Livewire
                                    ->afterStateHydrated(function (TextInput $component, $state) {
                                        if ($state !== null) {
                                            $component->state(number_format((float)$state, 0, ',', '.'));
                                        }
                                    })
                                    // Menghapus format sebelum nilai disimpan ke database
                                    ->dehydrateStateUsing(function (string $state): int {
                                        // Hapus semua karakter non-numerik kecuali koma (jika digunakan untuk desimal)
                                        // Untuk kasus ini, karena bigInteger, kita hanya butuh integer, jadi hapus semua non-digit
                                        return (int) str_replace(['.', 'Rp', ' '], '', $state);
                                    })
                                    // Inject JavaScript untuk format langsung saat mengetik
                                    ->extraAttributes([
                                        'x-data' => '{}', // Inisialisasi scope Alpine.js
                                        'x-on:input' => "
                                            let value = event.target.value.replace(/[^0-9]/g, ''); // Hapus semua non-digit
                                            if (value) {
                                                // Gunakan Intl.NumberFormat untuk format ribuan
                                                event.target.value = new Intl.NumberFormat('id-ID').format(value);
                                            }
                                        ",
                                    ]),
                                Textarea::make('keterangan')
                                    ->label('Keterangan')
                                    ->maxLength(255)
                                    ->nullable()
                                    ->rows(3)
                                    ->placeholder('Contoh: Pembelian bahan baku untuk proyek X')
                                    ->helperText('Berikan deskripsi singkat mengenai tujuan hutang.'),
                            ]),

                        Fieldset::make('Detail Tanggal & Status')
                            ->columns(1)
                            ->schema([
                                Grid::make(2)
                                    ->schema([
                                        DatePicker::make('tanggal')
                                            ->label('Tanggal Hutang')
                                            ->nullable()
                                            ->native(false)
                                            ->displayFormat('d/m/Y')
                                            ->placeholder('Pilih tanggal hutang dimulai')
                                            ->helperText('Tanggal kapan hutang ini terbentuk.'),
                                        DatePicker::make('jatuh_tempo')
                                            ->label('Jatuh Tempo')
                                            ->nullable()
                                            ->native(false)
                                            ->displayFormat('d/m/Y')
                                            ->placeholder('Pilih tanggal jatuh tempo')
                                            ->helperText('Batas waktu pembayaran hutang.'),
                                    ]),
                                Select::make('status')
                                    ->label('Status')
                                    ->options([
                                        'lunas' => 'Lunas',
                                        'belum_lunas' => 'Belum Lunas',
                                    ])
                                    ->default('belum_lunas')
                                    ->required()
                                    ->helperText('Pilih status pembayaran hutang.'),
                                FileUpload::make('bukti')
                                    ->label('Bukti')
                                    ->nullable()
                                    ->directory('bukti-hutang')
                                    ->disk('public')
                                    ->deletable()
                                    ->deleteUploadedFileUsing(function ($file) {
                                        if ($file) {
                                            Storage::disk('public')->delete($file);
                                        }
                                    })
                                    ->image()
                                    ->visibility('public')
                                    ->acceptedFileTypes(['image/*'])
                                    ->maxSize(2048)
                                    ->helperText('Unggah bukti terkait hutang (misal: faktur, nota).'),
                            ]),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make("No")->rowIndex(),
                TextColumn::make('user.name')
                    ->label('Customer')
                    ->wrap()
                    ->searchable()
                    ->sortable(),
                TextColumn::make('jml')
                    ->label('Jumlah Hutang')
                    ->money('IDR')
                    ->sortable(),
                TextColumn::make('tanggal')
                    ->label('Tanggal Hutang')
                    ->date('d/m/Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: false),
                TextColumn::make('jatuh_tempo')
                    ->label('Jatuh Tempo')
                    ->date('d/m/Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: false),
                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->colors([
                        'success' => 'lunas',
                        'danger' => 'belum_lunas',
                    ])
                    ->sortable(),
                TextColumn::make('bukti')
                    ->label('Bukti')
                    ->formatStateUsing(fn($state) => $state ? 'Ada' : 'Tidak Ada')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('created_at')
                    ->label('Dibuat Pada')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->label('Diperbarui Pada')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Filter Status')
                    ->options([
                        'lunas' => 'Lunas',
                        'belum_lunas' => 'Belum Lunas',
                    ]),
                \Filament\Tables\Filters\Filter::make('tanggal_hutang_range') // Menggunakan namespace penuh
                    ->label('Filter Rentang Tanggal Hutang')
                    ->form([
                        DatePicker::make('dari_tanggal')
                            ->label('Dari Tanggal')
                            ->native(false)
                            ->placeholder('DD/MM/YYYY'),
                        DatePicker::make('sampai_tanggal')
                            ->label('Sampai Tanggal')
                            ->native(false)
                            ->placeholder('DD/MM/YYYY'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['dari_tanggal'],
                                fn(Builder $query, $date): Builder => $query->whereDate('tanggal', '>=', $date),
                            )
                            ->when(
                                $data['sampai_tanggal'],
                                fn(Builder $query, $date): Builder => $query->whereDate('tanggal', '<=', $date),
                            );
                    }),

            ], layout: FiltersLayout::Modal)
            ->actions([
                ViewAction::make(),
                EditAction::make(),
                Tables\Actions\DeleteAction::make()
                    ->after(function ($record) {
                        if ($record->bukti) { // Hanya hapus jika ada gambar
                            Storage::disk('public')->delete($record->bukti);
                        }
                    })->icon('heroicon-o-trash')->color('danger')->button()->label('Hapus'),

                Action::make('bayarHutang')
                    ->label('Bayar Hutang')
                    ->icon('heroicon-o-currency-dollar')
                    ->color('success')
                    ->button()
                    ->url(fn(\App\Models\Hutang $record): string => static::getUrl('edit', ['record' => $record]))
                    ->hidden(fn(\App\Models\Hutang $record): bool => $record->status === 'lunas'),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()
                        ->after(function ($records) {
                            foreach ($records as $record) {
                                if ($record->bukti) { // Hanya hapus jika ada gambar
                                    Storage::disk('public')->delete($record->bukti);
                                }
                            }
                        }),
                ]),
            ]);
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                ComponentsSection::make('Informasi Umum Hutang') // Menggunakan alias ComponentsSection
                    ->columns(2) // Tampilkan dalam 2 kolom
                    ->schema([
                        // Grouping untuk entri kiri
                        Group::make()
                            ->schema([
                                TextEntry::make('user.name')
                                    ->label('Customer')
                                    ->color('primary')
                                    ->weight('bold'),
                                TextEntry::make('jml')
                                    ->label('Jumlah Hutang')
                                    ->money('IDR')
                                    ->color('success')
                                    ->weight('bold'),
                                TextEntry::make('keterangan')
                                    ->label('Keterangan')
                                    ->markdown() // Jika keterangan bisa berisi markdown
                                    ->columnSpanFull(), // Ambil lebar penuh di dalam kolom ini
                            ]),

                        // Grouping untuk entri kanan
                        Group::make()
                            ->schema([
                                TextEntry::make('tanggal')
                                    ->label('Tanggal Hutang')
                                    ->date('d F Y'), // Format tanggal yang lebih mudah dibaca
                                TextEntry::make('jatuh_tempo')
                                    ->label('Jatuh Tempo')
                                    ->date('d F Y')
                                    ->badge() // Tampilkan sebagai badge
                                    ->color(fn($state): string => $state ? 'warning' : 'gray'), // Warna badge tergantung pada status
                                TextEntry::make('status')
                                    ->label('Status')
                                    ->badge()
                                    ->color(fn(string $state): string => match ($state) {
                                        'lunas' => 'success',
                                        'belum_lunas' => 'danger',
                                        default => 'gray',
                                    }),
                                ImageEntry::make('bukti')
                                    ->label('Bukti')
                                    ->disk('public')
                                    ->height(150) // Tinggi gambar
                                    ->width(null) // Lebar otomatis
                                    ->columnSpanFull() // Ambil lebar penuh
                                    ->default(url('/images/placeholder.svg')) // Gambar default jika tidak ada bukti
                            ]),
                    ]),
                ComponentsSection::make('Daftar Pembayaran Hutang')
                    ->description('Semua pembayaran yang terkait dengan hutang ini.')
                    ->collapsible() // Bagian ini bisa dilipat
                    ->hidden(fn(\App\Models\Hutang $record) => !$record->bayarHutang()->exists())
                    ->schema([
                        RepeatableEntry::make('bayarHutang')
                            ->label('') // Sembunyikan label default RepeatableEntry
                            ->schema([
                                TextEntry::make('jml')
                                    ->label('Jumlah Bayar')
                                    ->money('IDR')
                                    ->weight('semibold'),
                                TextEntry::make('tanggal')
                                    ->label('Tanggal Pembayaran')
                                    ->date('d F Y'),
                                ImageEntry::make('bukti')
                                    ->label('Bukti Pembayaran')
                                    ->disk('public')
                                    ->height(100)
                                    ->width(null)
                                    ->default(url('/images/placeholder.svg')) // Gambar default jika tidak ada bukti
                                    ->placeholder('Tidak ada bukti'),
                            ])
                            ->columns(3) // Tampilkan setiap pembayaran dalam 3 kolom
                            ->contained(true), // Setiap pembayaran akan dibungkus dalam card
                    ]),
                // Tambahkan section lain jika ada kebutuhan detail lainnya
                ComponentsSection::make('Timeline')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('created_at')
                            ->label('Dibuat Pada')
                            ->dateTime('d F Y, H:i:s'),
                        TextEntry::make('updated_at')
                            ->label('Diperbarui Pada')
                            ->dateTime('d F Y, H:i:s'),
                    ]),
            ]);
    }


    public static function getRelations(): array
    {
        return [
            BayarHutangRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListHutangs::route('/'),
            'create' => Pages\CreateHutang::route('/create'),
            'edit' => Pages\EditHutang::route('/{record}/edit'),
        ];
    }
}
