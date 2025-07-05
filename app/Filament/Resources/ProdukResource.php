<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ProdukResource\Pages;
use App\Filament\Resources\ProdukResource\RelationManagers;
use App\Models\BahanBaku;
use App\Models\Kategori;
use App\Models\Produk;
use App\Models\Satuan;
use Filament\Forms;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Group;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Infolists\Components\Fieldset;
use Filament\Infolists\Components\Grid;
use Filament\Infolists\Components\ImageEntry;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\Section as ComponentsSection;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Infolist;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Support\Enums\FontWeight;
use Filament\Support\RawJs;
use Filament\Tables;
use Filament\Tables\Actions\DeleteBulkAction;
use Filament\Tables\Columns\TextInputColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Table;
use Icetalker\FilamentTableRepeater\Forms\Components\TableRepeater;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ProdukResource extends Resource
{
    protected static ?string $model = Produk::class;

    protected static ?string $navigationIcon = 'heroicon-o-archive-box';
    protected static ?string $navigationGroup = 'Master Data';
    protected static ?string $navigationLabel = 'Produk';
    protected static ?int $navigationSort = 5;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Informasi Utama Produk')
                    ->description('Isi data pokok produk seperti nama, kategori, satuan, dan slug otomatis.')
                    ->schema([
                        TextInput::make('nama_produk')
                            ->label('Nama Produk')
                            ->placeholder('Misalnya: Roti Tawar Gandum')
                            ->required()
                            ->maxLength(255)
                            ->autofocus()
                            ->live(onBlur: true)
                            ->afterStateUpdated(
                                fn(string $operation, $state, Forms\Set $set) =>
                                $operation === 'create' ? $set('slug', Str::slug($state)) : null
                            ),

                        TextInput::make('slug')
                            ->label('Slug (Tautan URL)')
                            ->placeholder('Otomatis dari nama produk')
                            ->unique(ignoreRecord: true)
                            ->required()
                            ->maxLength(255)
                            ->disabled()
                            ->dehydrated(),

                        Select::make('kategori_id')
                            ->label('Kategori')
                            ->options(Kategori::all()->pluck('kategori', 'id'))
                            ->required()
                            ->searchable()
                            ->preload()
                            ->placeholder('Pilih kategori produk'),

                        Select::make('satuan_id')
                            ->label('Satuan')
                            ->options(Satuan::all()->pluck('satuan', 'id'))
                            ->required()
                            ->searchable()
                            ->preload()
                            ->placeholder('Pilih satuan unit, contoh: pcs, botol, pack'),
                    ])->columns(2),

                Section::make('Harga & Stok')
                    ->description('Tentukan harga jual dan beli produk serta jumlah stok awal.')
                    ->schema([
                        TextInput::make('harga_beli')
                            ->label('Harga Modal')
                            ->required()
                            ->numeric()
                            ->default(0)
                            ->minValue(0)
                            ->prefix('Rp')
                            ->mask(RawJs::make('$money($input)'))
                            ->stripCharacters(','),

                        TextInput::make('harga_jual')
                            ->label('Harga Jual')
                            ->required()
                            ->numeric()
                            ->default(0)
                            ->minValue(0)
                            ->prefix('Rp')
                            ->mask(RawJs::make('$money($input)'))
                            ->stripCharacters(',')
                            ->live() // Tambahkan live untuk menghitung harga diskon secara real-time
                            ->afterStateUpdated(function (Get $get, Forms\Set $set) {
                                // Update harga setelah diskon jika promo diaktifkan
                                if ($get('promo') && $get('percentage')) {
                                    $hargaJual = (float) str_replace(',', '', $get('harga_jual'));
                                    $percentage = (float) $get('percentage');
                                    $hargaDiskon = $hargaJual - ($hargaJual * ($percentage / 100));
                                    $set('harga_setelah_diskon', round($hargaDiskon));
                                } else {
                                    $set('harga_setelah_diskon', (float) str_replace(',', '', $get('harga_jual')));
                                }
                            }),

                        TextInput::make('stok')
                            ->label('Stok Tersedia')
                            ->required()
                            ->numeric()
                            ->default(0)
                            ->minValue(0),

                        // Tambahkan field promo dan percentage di sini
                        Toggle::make('promo')
                            ->label('Aktifkan Promo?')
                            ->default(false)
                            ->live() // Penting agar perubahan ini memicu visibilitas percentage
                            ->inline(false)
                            ->helperText('Aktifkan jika produk ini sedang dalam masa promosi.'),

                        TextInput::make('percentage')
                            ->label('Persentase Diskon (%)')
                            ->requiredWith('promo') // Wajib diisi jika promo aktif
                            ->numeric()
                            ->minValue(0)
                            ->maxValue(100)
                            ->default(0)
                            ->suffix('%')
                            ->visible(fn(Get $get): bool => $get('promo')) // Hanya tampil jika promo aktif
                            ->live() // Agar perubahan persentase langsung menghitung harga diskon
                            ->afterStateUpdated(function (Get $get, Forms\Set $set) {
                                $hargaJual = (float) str_replace(',', '', $get('harga_jual'));
                                $percentage = (float) $get('percentage');
                                $hargaDiskon = $hargaJual - ($hargaJual * ($percentage / 100));
                                $set('harga_setelah_diskon', round($hargaDiskon));
                            }),

                        TextInput::make('harga_setelah_diskon')
                            ->label('Harga Setelah Diskon')
                            ->numeric()
                            ->prefix('Rp')
                            ->mask(RawJs::make('$money($input)'))
                            ->readOnly() // Hanya tampilan, tidak bisa diedit
                            ->dehydrated(false) // Tidak disimpan ke DB karena hasil perhitungan
                            ->default(fn(Get $get) => (float) str_replace(',', '', $get('harga_jual'))) // Defaultnya sama dengan harga jual
                            ->helperText('Harga otomatis dihitung setelah diskon jika promo aktif.')
                            ->columnSpan(fn(Get $get) => $get('promo') ? 1 : 2), // Mengambil 2 kolom jika promo tidak aktif


                    ])->columns(3),

                Section::make('Komposisi Bahan Baku')
                    ->description('Daftarkan bahan baku yang digunakan untuk memproduksi satu unit produk ini.')
                    ->schema([
                        Repeater::make('produkBahan') // Menggunakan Repeater, bukan TableRepeater (tidak ada bawaan)
                            ->relationship('produkBahan')
                            ->schema([
                                Select::make('bahan_baku_id')
                                    ->label('Bahan Baku')
                                    ->options(BahanBaku::all()->pluck('bahan', 'id'))
                                    ->required()
                                    ->searchable()
                                    ->preload()
                                    ->default(request('bahan_baku_id'))
                                    ->live() // Aktifkan live agar afterStateUpdated berjalan
                                    ->afterStateUpdated(function (?string $state, callable $set) { // Perubahan tipe hint
                                        if ($state) { // Pastikan state tidak null
                                            $bahan = BahanBaku::find($state);
                                            if ($bahan) {
                                                $set('satuan', $bahan->satuan->satuan);
                                                $set('stok_saat_ini', $bahan->stok); // Sesuaikan nama kolom untuk stok bahan baku
                                            }
                                        } else {
                                            $set('satuan', null);
                                            $set('stok_saat_ini', null);
                                        }
                                    })
                                    ->placeholder('Pilih bahan baku'),

                                TextInput::make('satuan')
                                    ->label('Satuan')
                                    ->dehydrated(false) // Tidak disimpan ke DB
                                    ->readOnly(),

                                TextInput::make('stok_saat_ini') // Kolom untuk menampilkan stok bahan baku saat ini
                                    ->label('Stok Bahan Baku')
                                    ->dehydrated(false) // Tidak disimpan ke DB
                                    ->readOnly(),

                                TextInput::make('qty')
                                    ->label('Kebutuhan per Unit')
                                    ->required()
                                    ->numeric()
                                    ->default(1)
                                    ->minValue(0.01)
                                    ->placeholder('Jumlah bahan yang dibutuhkan')
                                    ->maxValue(fn(Get $get) => BahanBaku::find($get('bahan_baku_id'))?->stok ?? 0)

                                    ->suffix(function (Forms\Get $get) {
                                        // Tampilkan satuan dari bahan baku yang terpilih
                                        $bahan = BahanBaku::find($get('bahan_baku_id'));
                                        return $bahan && $bahan->satuan ? $bahan->satuan->satuan : null;
                                    }),
                            ])
                            ->columns(4) // Sesuaikan jumlah kolom di repeater
                            ->reorderable()
                            ->cloneable()
                            ->collapsible()
                            ->itemLabel(fn(array $state): ?string => empty($state['bahan_baku_id']) ? 'Bahan Baru' : (
                                ($bahan = BahanBaku::find($state['bahan_baku_id'])) ?
                                $bahan->bahan . ' (' . ($bahan->satuan->satuan ?? 'Satuan Tidak Diketahui') . ') - ' . $state['qty'] :
                                'Bahan Tidak Ditemukan'
                            ))
                            ->columnSpanFull(),
                    ])->columnSpanFull(),

                Section::make('Detail & Media Produk')
                    ->description('Lengkapi deskripsi produk dan unggah gambar yang mewakili produk.')
                    ->schema([
                        Textarea::make('deskripsi')
                            ->label('Deskripsi')
                            ->placeholder('Tuliskan penjelasan lengkap mengenai produk...')
                            ->nullable()
                            ->columnSpanFull(),

                        FileUpload::make('image')
                            ->label('Gambar Produk')
                            ->image()
                            ->directory('product-images')
                            ->visibility('public')
                            ->nullable()
                            ->deletable(true)
                            ->deleteUploadedFileUsing(function ($file) {
                                Storage::disk('public')->delete($file);
                            })
                            ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])
                            ->maxSize(2048)
                            ->hint('Format yang didukung: JPG, PNG, WEBP (maksimal 2MB).'),

                        Toggle::make('is_active')
                            ->label('Status Produk')
                            ->default(true)
                            ->inline(false)
                            ->helperText('Nonaktifkan produk jika tidak tersedia atau tidak dijual sementara.'),
                    ])->columns(1),
            ]);
    }


    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\ImageColumn::make('image')
                    ->label('Gambar')
                    ->square()
                    ->defaultImageUrl(url('/images/placeholder.svg')), // Opsional: Tambahkan placeholder

                Tables\Columns\TextColumn::make('nama_produk')
                    ->label('Nama Produk')
                    ->searchable()
                    ->sortable()
                    ->wrap(), // Agar teks panjang bisa dibungkus

                Tables\Columns\TextColumn::make('kategori.kategori')
                    ->label('Kategori')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('satuan.satuan')
                    ->label('Satuan Produk')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('harga_beli')
                    ->label('Harga Modal')
                    ->numeric()
                    ->money('IDR', locale: 'id') // Format sebagai Rupiah Indonesia
                    ->sortable(),

                Tables\Columns\TextColumn::make('harga_jual')
                    ->label('Harga Jual')
                    ->numeric()
                    ->money('IDR', locale: 'id') // Format sebagai Rupiah Indonesia
                    ->sortable(),

                // Kolom untuk promo
                ToggleColumn::make('promo')
                    ->label('Promo Aktif'),

                // Kolom untuk percentage, hanya tampil jika promo aktif
                Tables\Columns\TextColumn::make('percentage')
                    ->label('Diskon (%)')
                    ->formatStateUsing(fn(int $state) => $state . '%') // Tampilkan sebagai persentase
                    ->visibleFrom('md') // Sembunyikan di layar kecil
                    ->toggleable(isToggledHiddenByDefault: false), // Defaultnya tidak tersembunyi

                // Kolom untuk harga setelah diskon (perhitungan)
                Tables\Columns\TextColumn::make('harga_setelah_diskon')
                    ->label('Harga Promo')
                    ->state(function (Model $record): string {
                        if ($record->promo && $record->percentage > 0) {
                            $hargaDiskon = $record->harga_jual - ($record->harga_jual * ($record->percentage / 100));
                            return 'Rp' . number_format($hargaDiskon, 0, ',', '.');
                        }
                        return 'Tidak Ada Promo';
                    })
                    ->color(fn(Model $record): string => $record->promo && $record->percentage > 0 ? 'success' : 'gray')
                    ->weight(FontWeight::Bold)
                    ->sortable(false), // Tidak perlu disortir karena ini kolom kalkulasi

                TextInputColumn::make('stok')
                    ->type('number')
                    ->sortable()
                    ->searchable()
                    ->label('Stok'),

                ToggleColumn::make('is_active'),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Dibuat Pada')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true), // Sembunyikan secara default

                Tables\Columns\TextColumn::make('updated_at')
                    ->label('Diperbarui Pada')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true), // Sembunyikan secara default
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('kategori_id')
                    ->label('Filter Kategori')
                    ->options(Kategori::all()->pluck('kategori', 'id'))
                    ->placeholder('Pilih Kategori'),

                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Status Aktif')
                    ->boolean()
                    ->trueLabel('Aktif')
                    ->falseLabel('Nonaktif')
                    ->placeholder('Semua')
                    ->attribute('is_active'), // Pastikan ini mengacu pada kolom yang benar

                // Filter untuk promo
                Tables\Filters\TernaryFilter::make('promo')
                    ->label('Sedang Promo')
                    ->boolean()
                    ->trueLabel('Ya')
                    ->falseLabel('Tidak')
                    ->placeholder('Semua'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\ViewAction::make(), // Tambahkan action View
                Tables\Actions\DeleteAction::make()->after(function ($record) {
                    Storage::disk('public')->delete($record->image); // Hapus file gambar dari storage
                })->icon('heroicon-o-trash')->color('danger')->button()->label('Hapus'),

            ])
            ->bulkActions([
                DeleteBulkAction::make()
                    ->after(function ($records) {
                        foreach ($records as $record) {
                            Storage::disk('public')->delete($record->image); // Hapus file gambar dari storage
                            // File::delete(public_path('storage\\' . $record->image));
                        }
                    }),
            ])
            ->emptyStateActions([
                Tables\Actions\CreateAction::make(), // Tambahkan action untuk membuat data baru jika tabel kosong
            ]);
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                ComponentsSection::make('Informasi Umum Produk')
                    ->schema([
                        Grid::make(3) // Menggunakan Grid untuk tata letak kolom
                            ->schema([
                                ImageEntry::make('image')
                                    ->label('Gambar Produk')
                                    ->square()
                                    ->width("100%")
                                    ->height("100%")
                                    ->defaultImageUrl(url('/images/placeholder.svg'))
                                    ->columnSpan(1), // Gambar mengambil 1 kolom

                                Fieldset::make('Detail Produk')
                                    ->schema([
                                        TextEntry::make('nama_produk')
                                            ->label('Nama Produk')
                                            ->weight(FontWeight::Bold)
                                            ->size(TextEntry\TextEntrySize::Large),
                                        TextEntry::make('slug')
                                            ->label('Slug')
                                            ->copyable() // Memungkinkan salin teks slug
                                            ->size(TextEntry\TextEntrySize::Small),
                                        TextEntry::make('kategori.kategori')
                                            ->label('Kategori')
                                            ->badge(), // Menampilkan kategori sebagai badge
                                        TextEntry::make('satuan.satuan')
                                            ->label('Satuan')
                                            ->badge(), // Menampilkan satuan sebagai badge
                                        TextEntry::make('is_active')
                                            ->label('Status')
                                            ->badge() // Menampilkan status sebagai badge
                                            ->formatStateUsing(fn(bool $state) => $state ? 'Aktif' : 'Nonaktif')
                                            ->color(fn(bool $state) => $state ? 'success' : 'danger'),
                                    ])->columnSpan(2), // Detail produk mengambil 2 kolom
                            ]),
                    ])->columns(1), // Bagian ini akan memiliki 1 kolom utama (grid di dalamnya)


                ComponentsSection::make('Harga & Stok')
                    ->schema([
                        Grid::make(3)
                            ->schema([
                                TextEntry::make('harga_beli')
                                    ->label('Harga Modal')
                                    ->numeric()
                                    ->money('IDR', locale: 'id')
                                    ->weight(FontWeight::Medium),
                                TextEntry::make('harga_jual')
                                    ->label('Harga Jual')
                                    ->numeric()
                                    ->money('IDR', locale: 'id')
                                    ->weight(FontWeight::Bold),

                                TextEntry::make('promo')
                                    ->label('Status Promo')
                                    ->badge()
                                    ->formatStateUsing(fn(bool $state) => $state ? 'Aktif' : 'Tidak Aktif')
                                    ->color(fn(bool $state) => $state ? 'success' : 'gray'),

                                TextEntry::make('percentage')
                                    ->label('Persentase Diskon')
                                    ->formatStateUsing(fn(?int $state) => $state ? $state . '%' : '0%')
                                    ->visible(fn($record) => $record->promo), // Hanya tampil jika promo aktif

                                TextEntry::make('harga_setelah_diskon')
                                    ->label('Harga Setelah Diskon')
                                    ->state(function (Model $record): string {
                                        if ($record->promo && $record->percentage > 0) {
                                            $hargaDiskon = $record->harga_jual - ($record->harga_jual * ($record->percentage / 100));
                                            return 'Rp' . number_format($hargaDiskon, 0, ',', '.');
                                        }
                                        return 'Tidak Ada Diskon';
                                    })
                                    ->color(fn($record) => $record->promo && $record->percentage > 0 ? 'primary' : 'gray')
                                    ->weight(FontWeight::Bold)
                                    ->visible(fn($record) => $record->promo), // Hanya tampil jika promo aktif

                                TextEntry::make('stok')
                                    ->label('Stok Tersedia')
                                    ->numeric()
                                    ->suffix(' unit')
                                    ->color('primary')
                                    ->weight(FontWeight::Bold),
                            ]),
                    ])->columns(1),


                ComponentsSection::make('Komposisi Bahan Baku')
                    ->description('Daftar bahan baku yang digunakan untuk memproduksi satu unit produk ini.')
                    ->schema([
                        RepeatableEntry::make('produkBahan')
                            ->hiddenLabel() // Sembunyikan label bawaan repeater
                            ->schema([
                                Grid::make(3) // Grid untuk setiap baris bahan baku
                                    ->schema([
                                        TextEntry::make('bahanBaku.bahan')
                                            ->label('Bahan Baku')
                                            ->weight(FontWeight::Bold),
                                        TextEntry::make('qty')
                                            ->label('Kebutuhan per Unit')
                                            ->numeric()
                                            ->suffix(fn($record) => $record->bahanBaku->satuan->satuan ?? ''),

                                        TextEntry::make('bahanBaku.stok')
                                            ->label('Stok Bahan Baku Saat Ini')
                                            ->numeric()
                                            ->suffix(fn($record) => $record->bahanBaku->satuan->satuan ?? '')
                                            ->color('secondary'),

                                    ]),
                            ])
                            ->columns(1) // Repeater itu sendiri hanya memiliki 1 kolom
                    ]),

                ComponentsSection::make('Deskripsi Produk')
                    ->schema([
                        TextEntry::make('deskripsi')
                            ->label('Deskripsi Lengkap')
                            ->html() // Render HTML jika deskripsi disimpan sebagai RichEditor
                            ->placeholder('Tidak ada deskripsi tersedia.')
                            ->prose(), // Untuk styling markdown/HTML dasar
                    ])->columns(1),

                ComponentsSection::make('Informasi Lainnya')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextEntry::make('created_at')
                                    ->label('Dibuat Pada')
                                    ->dateTime(),
                                TextEntry::make('updated_at')
                                    ->label('Diperbarui Pada')
                                    ->dateTime(),
                            ]),
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
            'index' => Pages\ListProduks::route('/'),
            'create' => Pages\CreateProduk::route('/create'),
            'edit' => Pages\EditProduk::route('/{record}/edit'),
        ];
    }
}
