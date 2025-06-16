<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PiutangResource\Pages;
use App\Filament\Resources\PiutangResource\RelationManagers;
use App\Filament\Resources\PiutangResource\RelationManagers\BayarPiutangRelationManager;
use App\Models\Piutang;
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
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Facades\Storage;

class PiutangResource extends Resource
{
    protected static ?string $model = Piutang::class;

    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-list';
    protected static ?string $navigationGroup = 'Pencatatan';
    protected static ?string $navigationLabel = 'Daftar Piutang';
    protected static ?int $navigationSort = 12;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Informasi Detail Piutang')
                    ->description('Isi detail informasi piutang di bawah ini.')
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
                                    ->placeholder('Pilih pengguna yang memiliki piutang ini')
                                    ->helperText('Pilih siapa yang memiliki piutang ini.'),
                                TextInput::make('jml')
                                    ->label('Jumlah Piutang')
                                    ->numeric()
                                    ->default(0)
                                    ->required()
                                    ->prefix('Rp')
                                    ->maxValue(999999999999999)
                                    ->placeholder('Contoh: 750.000')
                                    ->helperText('Masukkan jumlah total piutang dalam Rupiah. Angka akan diformat otomatis saat diketik.')
                                    ->live()
                                    ->afterStateHydrated(function (TextInput $component, $state) {
                                        if ($state !== null) {
                                            $component->state(number_format((float)$state, 0, ',', '.'));
                                        }
                                    })
                                    ->dehydrateStateUsing(function (string $state): int {
                                        return (int) str_replace(['.', 'Rp', ' '], '', $state);
                                    })
                                    ->extraAttributes([
                                        'x-data' => '{}',
                                        'x-on:input' => "
                                            let value = event.target.value.replace(/[^0-9]/g, '');
                                            if (value) {
                                                event.target.value = new Intl.NumberFormat('id-ID').format(value);
                                            }
                                        ",
                                    ]),
                                Textarea::make('keterangan')
                                    ->label('Keterangan')
                                    ->maxLength(255)
                                    ->nullable()
                                    ->rows(3)
                                    ->placeholder('Contoh: Penjualan barang ke pelanggan A')
                                    ->helperText('Berikan deskripsi singkat mengenai asal piutang.'),
                            ]),

                        Fieldset::make('Detail Tanggal & Status')
                            ->columns(1)
                            ->schema([
                                Grid::make(2)
                                    ->schema([
                                        DatePicker::make('tanggal')
                                            ->label('Tanggal Piutang')
                                            ->nullable()
                                            ->native(false)
                                            ->displayFormat('d/m/Y')
                                            ->placeholder('Pilih tanggal piutang terbentuk')
                                            ->helperText('Tanggal kapan piutang ini terbentuk.'),
                                        DatePicker::make('jatuh_tempo')
                                            ->label('Jatuh Tempo')
                                            ->nullable()
                                            ->native(false)
                                            ->displayFormat('d/m/Y')
                                            ->placeholder('Pilih tanggal jatuh tempo')
                                            ->helperText('Batas waktu penerimaan piutang.'),
                                    ]),
                                Select::make('status')
                                    ->label('Status')
                                    ->options([
                                        'lunas' => 'Lunas',
                                        'belum_lunas' => 'Belum Lunas',
                                    ])
                                    ->default('belum_lunas')
                                    ->required()
                                    ->helperText('Pilih status piutang.'),
                                FileUpload::make('bukti')
                                    ->label('Bukti')
                                    ->nullable()
                                    ->directory('bukti-piutang')
                                    ->disk('public')
                                    ->image()
                                    ->visibility('public')
                                    ->acceptedFileTypes(['image/*'])
                                    ->maxSize(2048)
                                    ->helperText('Unggah bukti terkait piutang (misal: invoice, surat perjanjian).'),
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
                    ->searchable()
                    ->wrap()
                    ->sortable(),
                TextColumn::make('jml')
                    ->label('Jumlah Piutang')
                    ->formatStateUsing(fn($state) => 'Rp ' . number_format($state, 0, ',', '.'))
                    ->sortable(),
                TextColumn::make('tanggal')
                    ->label('Tanggal Piutang')
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
                    ->color(fn(string $state): string => match ($state) {
                        'lunas' => 'success',
                        'belum_lunas' => 'danger',
                        default => 'gray',
                    })
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


                Action::make('terimaPiutang')
                    ->label('Terima Piutang')
                    ->icon('heroicon-o-currency-dollar')
                    ->button()
                    ->color('success')
                    ->url(fn(Piutang $record): string => static::getUrl('edit', ['record' => $record]))
                    ->hidden(fn(Piutang $record): bool => $record->status === 'lunas'),
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
                ComponentsSection::make('Informasi Utama Piutang')
                    ->description('Detail lengkap mengenai piutang ini.')
                    ->columns(2)
                    ->schema([
                        Group::make()
                            ->schema([
                                TextEntry::make('user.name')
                                    ->label('Customer')
                                    ->icon('heroicon-o-user')
                                    ->color('primary')
                                    ->weight('bold')
                                    ->size(TextEntry\TextEntrySize::Large),
                                TextEntry::make('jml')
                                    ->label('Jumlah Piutang')
                                    ->money('IDR')
                                    ->icon('heroicon-o-banknotes')
                                    ->color('success')
                                    ->weight('bold')
                                    ->size(TextEntry\TextEntrySize::Large),
                                TextEntry::make('keterangan')
                                    ->label('Keterangan')
                                    ->markdown()
                                    ->badge()
                                    ->color('info')
                                    ->columnSpanFull()
                                    ->placeholder('Tidak ada keterangan.'),
                            ]),

                        Group::make()
                            ->schema([
                                TextEntry::make('tanggal')
                                    ->label('Tanggal Piutang')
                                    ->date('d F Y')
                                    ->icon('heroicon-o-calendar'),
                                TextEntry::make('jatuh_tempo')
                                    ->label('Jatuh Tempo')
                                    ->date('d F Y')
                                    ->badge()
                                    ->icon('heroicon-o-exclamation-triangle')
                                    ->color(function (string $state): string {
                                        return $state ? 'warning' : 'gray';
                                    }),
                                TextEntry::make('status')
                                    ->label('Status')
                                    ->badge()
                                    ->icon(function (string $state): string {
                                        return match ($state) {
                                            'lunas' => 'heroicon-o-check-badge',
                                            'belum_lunas' => 'heroicon-o-x-circle',
                                            default => 'heroicon-o-question-mark-circle',
                                        };
                                    })
                                    ->color(fn(string $state): string => match ($state) {
                                        'lunas' => 'success',
                                        'belum_lunas' => 'danger',
                                        default => 'gray',
                                    }),
                                ImageEntry::make('bukti')
                                    ->label('Bukti')
                                    ->disk('public')
                                    ->height(150)
                                    ->default(url('images/placeholder.svg'))
                                    ->width(null)
                                    ->columnSpanFull()
                                    ->placeholder('Tidak ada bukti gambar.'),
                            ]),
                    ]),

                ComponentsSection::make('Informasi Tambahan')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('created_at')
                            ->label('Dibuat Pada')
                            ->dateTime('d F Y, H:i:s')
                            ->icon('heroicon-o-clock'),
                        TextEntry::make('updated_at')
                            ->label('Diperbarui Pada')
                            ->dateTime('d F Y, H:i:s')
                            ->icon('heroicon-o-arrow-path'),
                    ]),

                ComponentsSection::make('Daftar Penerimaan Piutang')
                    ->description('Semua penerimaan yang terkait dengan piutang ini.')
                    ->collapsible()
                    ->hidden(fn(Piutang $record) => !$record->bayarPiutang()->exists())
                    ->schema([
                        RepeatableEntry::make('bayarPiutang')
                            ->label('')
                            ->schema([
                                TextEntry::make('jml')
                                    ->label('Jumlah Terima')
                                    ->money('IDR')
                                    ->weight('semibold'),
                                TextEntry::make('tanggal')
                                    ->label('Tanggal Penerimaan')
                                    ->date('d F Y'),
                                ImageEntry::make('bukti')
                                    ->label('Bukti Penerimaan')
                                    ->disk('public')
                                    ->height(100)
                                    ->default(url('images/placeholder.svg'))
                                    ->width(null)
                                    ->placeholder('Tidak ada bukti'),
                            ])
                            ->columns(3)
                            ->contained(true),
                    ]),
            ]);
    }
    public static function getRelations(): array
    {
        return [
            BayarPiutangRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPiutangs::route('/'),
            'create' => Pages\CreatePiutang::route('/create'),
            'edit' => Pages\EditPiutang::route('/{record}/edit'),
        ];
    }
}
