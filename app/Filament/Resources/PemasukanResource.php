<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PemasukanResource\Pages;
use App\Filament\Resources\PemasukanResource\RelationManagers;
use App\Models\KategoriCatatan;
use App\Models\Pemasukan;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Section as ComponentsSection;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Infolists\Components\Fieldset;
use Filament\Infolists\Components\Grid;
use Filament\Infolists\Components\ImageEntry;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Infolist;
use Filament\Resources\Resource;
use Filament\Support\Enums\FontWeight;
use Filament\Support\RawJs;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class PemasukanResource extends Resource
{
    protected static ?string $model = Pemasukan::class;

    protected static ?string $navigationIcon = 'heroicon-o-currency-euro'; // Icon yang sesuai

    protected static ?string $navigationGroup = 'Pencatatan';
    protected static ?string $navigationLabel = 'Pemasukan';
    protected static ?int $navigationSort = 10;



    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                ComponentsSection::make('Detail Pemasukan')
                    ->description('Catat informasi detail mengenai setiap pemasukan.')
                    ->schema([
                        // Hidden field for user_id, automatically populated
                        Forms\Components\Hidden::make('user_id')
                            ->default(Auth::id()) // Set default to current authenticated user's ID
                            ->dehydrated(true)
                            ->required(), // User ID should always be present

                        Select::make('kategori_catatan_id')
                            ->label('Kategori Pemasukan')
                            // Menggunakan mapWithKeys untuk memastikan kategori selalu string
                            ->options(
                                KategoriCatatan::where('type', 'pemasukan')->get()->mapWithKeys(function ($kategori) {
                                    return [$kategori->id => (string) $kategori->name];
                                })->toArray()
                            )
                            ->required()
                            ->searchable()
                            ->preload()
                            ->placeholder('Pilih kategori pemasukan'),

                        TextInput::make('nama_pemasukan')
                            ->label('Nama/Judul Pemasukan')
                            ->placeholder('Misalnya: Penjualan produk, Dana investasi')
                            ->maxLength(255)
                            ->nullable(), // Sesuai dengan skema Anda yang nullable

                        TextInput::make('jumlah')
                            ->label('Jumlah (Rp)')
                            ->required()
                            ->numeric()
                            ->default(0)
                            ->minValue(0)
                            ->prefix('Rp')
                            ->mask(RawJs::make('$money($input)'))
                            ->stripCharacters(','),

                        DatePicker::make('tanggal_pemasukan')
                            ->label('Tanggal Pemasukan')
                            ->required()
                            ->default(now()) // Default ke tanggal hari ini
                            ->native(false) // Menggunakan date picker kustom Filament
                            ->displayFormat('d/m/Y'),

                        RichEditor::make('keterangan')
                            ->label('Keterangan Tambahan')
                            ->placeholder('Detail lebih lanjut mengenai pemasukan ini...')
                            ->nullable()
                            ->columnSpanFull(),

                        FileUpload::make('image')
                            ->label('Gambar/Bukti Pemasukan')
                            ->image()
                            ->directory('income-images') // Direktori penyimpanan gambar
                            ->visibility('public')
                            ->nullable() // Telah diubah menjadi nullable sesuai skema
                            ->deletable(true)
                            ->columnSpan(['lg' => 2]) // Lebar kolom pada layar besar
                            ->deleteUploadedFileUsing(function ($file) {
                                // Hapus file dari storage saat dihapus dari form
                                Storage::disk('public')->delete($file);
                            })
                            ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])
                            ->maxSize(2048) // Maksimal 2MB
                            ->hint('Unggah foto atau scan bukti (JPG, PNG, WEBP, maks 2MB).'),
                    ])->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\ImageColumn::make('image')
                    ->label('Bukti')
                    ->square()
                    ->defaultImageUrl(url('/images/placeholder.svg')), // Placeholder jika tidak ada gambar

                Tables\Columns\TextColumn::make('user.name') // Kolom untuk menampilkan nama pengguna
                    ->label('Dibuat Oleh')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('kategori_catatan.name')
                    ->label('Kategori')
                    ->searchable()
                    ->sortable()
                    ->badge(),

                Tables\Columns\TextColumn::make('nama_pemasukan')
                    ->label('Nama Pemasukan')
                    ->searchable()
                    ->sortable()
                    ->wrap(),

                Tables\Columns\TextColumn::make('jumlah')
                    ->label('Jumlah')
                    ->numeric()
                    ->money('IDR', locale: 'id') // Format sebagai Rupiah Indonesia
                    ->sortable()
                    ->weight(FontWeight::Bold),

                Tables\Columns\TextColumn::make('tanggal_pemasukan')
                    ->label('Tanggal')
                    ->date('d M Y') // Format tanggal yang mudah dibaca
                    ->sortable(),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Dibuat Pada')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('updated_at')
                    ->label('Diperbarui Pada')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('user_id') // Filter berdasarkan pengguna
                    ->label('Filter Pengguna')
                    ->options(User::where('role', '!=', 'customer')->pluck('name', 'id'))
                    ->placeholder('Pilih Pengguna'),

                Tables\Filters\SelectFilter::make('kategori_catatan_id')
                    ->label('Filter Kategori')
                    ->options(KategoriCatatan::where('type', 'pemasukan')->pluck('name', 'id'))
                    ->placeholder('Pilih Kategori'),

                Tables\Filters\Filter::make('tanggal_pemasukan')
                    ->form([
                        DatePicker::make('dari_tanggal')
                            ->label('Dari Tanggal')
                            ->placeholder('DD/MM/YYYY'),
                        DatePicker::make('sampai_tanggal')
                            ->label('Sampai Tanggal')
                            ->placeholder('DD/MM/YYYY'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['dari_tanggal'],
                                fn(Builder $query, $date): Builder => $query->whereDate('tanggal_pemasukan', '>=', $date),
                            )
                            ->when(
                                $data['sampai_tanggal'],
                                fn(Builder $query, $date): Builder => $query->whereDate('tanggal_pemasukan', '<=', $date),
                            );
                    }),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(), // Tambahkan aksi View
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make()
                    ->after(function ($record) {
                        // Hapus file gambar dari storage saat record dihapus
                        if ($record->image) { // Hanya hapus jika ada gambar
                            Storage::disk('public')->delete($record->image);
                        }
                    })->icon('heroicon-o-trash')->color('danger')->button()->label('Hapus'),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()
                        ->after(function ($records) {
                            foreach ($records as $record) {
                                if ($record->image) { // Hanya hapus jika ada gambar
                                    Storage::disk('public')->delete($record->image);
                                }
                            }
                        }),
                ]),
            ])
            ->emptyStateActions([
                Tables\Actions\CreateAction::make(),
            ]);
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Section::make('Informasi Lengkap Pemasukan')
                    ->schema([
                        Grid::make(3)
                            ->schema([
                                ImageEntry::make('image')
                                    ->label('Bukti Pemasukan')
                                    ->square()
                                    ->width("100%")
                                    ->height("100%")
                                    ->defaultImageUrl(url('/images/placeholder.svg'))
                                    ->columnSpan(1),

                                Fieldset::make('Detail Transaksi')
                                    ->schema([
                                        TextEntry::make('user.name') // Menampilkan nama pengguna
                                            ->label('Dibuat Oleh')
                                            ->weight(FontWeight::Bold),
                                        TextEntry::make('kategori_catatan.name')
                                            ->label('Kategori')
                                            ->badge()
                                            ->color('success'), // Warna badge untuk pemasukan
                                        TextEntry::make('nama_pemasukan')
                                            ->label('Nama/Judul')
                                            ->weight(FontWeight::Bold)
                                            ->size(TextEntry\TextEntrySize::Large),
                                        TextEntry::make('jumlah')
                                            ->label('Jumlah Pemasukan')
                                            ->numeric()
                                            ->money('IDR', locale: 'id')
                                            ->weight(FontWeight::Bold)
                                            ->color('success'), // Warna jumlah untuk pemasukan
                                        TextEntry::make('tanggal_pemasukan')
                                            ->label('Tanggal')
                                            ->date('d F Y')
                                            ->weight(FontWeight::Medium),
                                    ])->columnSpan(2),
                            ]),
                        Section::make('Keterangan')
                            ->schema([
                                TextEntry::make('keterangan')
                                    ->label('Keterangan Tambahan')
                                    ->html() // Render HTML jika disimpan dari RichEditor
                                    ->placeholder('Tidak ada keterangan.'),
                            ])->collapsible(),

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
            'index' => Pages\ListPemasukans::route('/'),
            'create' => Pages\CreatePemasukan::route('/create'),
            'edit' => Pages\EditPemasukan::route('/{record}/edit'),
        ];
    }
}
