<?php

namespace App\Filament\Resources\HutangResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Actions\CreateAction;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class BayarHutangRelationManager extends RelationManager
{
    protected static string $relationship = 'BayarHutang';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                TextInput::make('jml')
                    ->label('Jumlah Bayar')
                    ->numeric()
                    ->default(0)
                    ->required()
                    ->prefix('Rp')
                    ->maxValue(999999999999999)
                    ->placeholder('Contoh: 50.000')
                    ->helperText('Masukkan jumlah yang dibayarkan.')
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
                DatePicker::make('tanggal')
                    ->label('Tanggal Pembayaran')
                    ->nullable()
                    ->native(false)
                    ->displayFormat('d/m/Y')
                    ->placeholder('Pilih tanggal pembayaran')
                    ->helperText('Tanggal pembayaran ini dilakukan.'),
                FileUpload::make('bukti')
                    ->label('Bukti Pembayaran')
                    ->nullable()
                    ->directory('bukti-bayar-hutang')
                    ->disk('public')
                    ->image()
                    ->deletable()
                    ->deleteUploadedFileUsing(function ($file) {
                        Storage::disk('public')->delete($file);
                    })
                    ->visibility('public')
                    ->acceptedFileTypes(['image/*'])
                    ->maxSize(2048)
                    ->columnSpan(['lg' => 2])
                    ->helperText('Unggah bukti pembayaran (misal: struk, tangkapan layar transfer).'),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('tanggal')
            ->columns([
                TextColumn::make("No")->rowIndex(),
                TextColumn::make('jml')
                    ->label('Jumlah Bayar')
                    ->money('IDR')
                    ->sortable(),
                TextColumn::make('tanggal')
                    ->label('Tanggal Pembayaran')
                    ->date('d/m/Y')
                    ->sortable(),
                ImageColumn::make('bukti')
                    ->label('Bukti Pembayaran')
                    ->default(url('/images/placeholder.svg'))
                    ->size(50),
                TextColumn::make('created_at')
                    ->label('Dibuat Pada')
                    ->dateTime('d/m/Y H:i:s')
                    ->sortable(),
            ])
            ->filters([
                Filter::make('has_bukti')
                    ->label('Ada Bukti')
                    ->query(fn(Builder $query): Builder => $query->whereNotNull('bukti'))
            ], layout: FiltersLayout::Modal)
            ->headerActions([
                CreateAction::make(),
            ])
            ->actions([
                EditAction::make(),
                Tables\Actions\DeleteAction::make()
                    ->after(function ($record) {
                        if ($record->bukti) { // Hanya hapus jika ada gambar
                            Storage::disk('public')->delete($record->bukti);
                        }
                    })->icon('heroicon-o-trash')->color('danger')->button()->label('Hapus'),
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
}
