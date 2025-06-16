<?php

namespace App\Filament\Resources\PiutangResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Actions\Action;
use Filament\Tables\Actions\CreateAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Facades\Storage;

class BayarPiutangRelationManager extends RelationManager
{
    protected static string $relationship = 'BayarPiutang';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                TextInput::make('jml')
                    ->label('Jumlah Terima')
                    ->numeric()
                    ->default(0)
                    ->required()
                    ->prefix('Rp')
                    ->maxValue(999999999999999)
                    ->placeholder('Contoh: 50.000')
                    ->helperText('Masukkan jumlah yang diterima.')
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
                    ->label('Tanggal Penerimaan')
                    ->nullable()
                    ->native(false)
                    ->displayFormat('d/m/Y')
                    ->placeholder('Pilih tanggal penerimaan')
                    ->helperText('Tanggal penerimaan ini dilakukan.'),
                FileUpload::make('bukti')
                    ->label('Bukti Penerimaan')
                    ->nullable()
                    ->disk('public')
                    ->image()
                    ->deletable()
                    ->deleteUploadedFileUsing(function ($file) {
                        Storage::disk('public')->delete($file);
                    })
                    ->visibility('public')
                    ->acceptedFileTypes(['image/*'])
                    ->columnSpan(['lg' => 2])
                    ->maxSize(2048)
                    ->helperText('Unggah bukti penerimaan (misal: kwitansi, tangkapan layar transfer).'),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('tanggal')
            ->columns([
                TextColumn::make("No")->rowIndex(),
                TextColumn::make('jml')
                    ->label('Jumlah Terima')
                    ->money('IDR')
                    ->sortable(),
                TextColumn::make('tanggal')
                    ->label('Tanggal Penerimaan')
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
            ])
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
                Action::make('deleteBukti')
                    ->label('Hapus Bukti')
                    ->icon('heroicon-o-trash')
                    ->color('danger')
                    ->visible(fn(\App\Models\BayarPiutang $record): bool => (bool) $record->bukti)
                    ->requiresConfirmation()
                    ->action(function (\App\Models\BayarPiutang $record): void {
                        if ($record->bukti) {
                            Storage::disk('public')->delete($record->bukti);
                            $record->update(['bukti' => null]);
                            Notification::make()
                                ->title('Bukti berhasil dihapus')
                                ->success()
                                ->send();
                        } else {
                            Notification::make()
                                ->title('Tidak ada bukti untuk dihapus.')
                                ->warning()
                                ->send();
                        }
                    }),
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
