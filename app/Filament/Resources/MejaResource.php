<?php

namespace App\Filament\Resources;

use App\Filament\Resources\MejaResource\Pages;
use App\Filament\Resources\MejaResource\RelationManagers;
use App\Models\Meja;
use Filament\Forms;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Infolists\Components\Group;
use Filament\Infolists\Components\Section as ComponentsSection;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Components\TextEntry\TextEntrySize;
use Filament\Infolists\Infolist;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\SelectColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class MejaResource extends Resource
{
    protected static ?string $model = Meja::class;

    protected static ?string $navigationIcon = 'heroicon-o-view-columns';
    protected static ?string $navigationGroup = 'Master Data';
    protected static ?string $navigationLabel = 'Meja';
    protected static ?int $navigationSort = 4;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Informasi Meja')
                    ->description('Isi detail informasi meja di bawah ini. Nomor meja harus unik.')
                    ->columns(2) // Mengatur tata letak 2 kolom
                    ->schema([
                        TextInput::make('nama')
                            ->label('Nomor / Nama Meja')
                            ->required()
                            ->unique(ignoreRecord: true) // Pastikan nama meja unik, abaikan record saat ini saat update
                            ->maxLength(255)
                            ->placeholder('Contoh: M01, Meja Bar, Ruang VIP 1')
                            ->helperText('Masukkan identifikasi unik untuk meja ini.'),

                        Select::make('status')
                            ->label('Status Meja')
                            ->options([
                                'tersedia' => 'Tersedia',
                                'dipakai' => 'Dipakai',
                            ])
                            ->default('tersedia')
                            ->required()
                            ->native(false) // Menggunakan dropdown Filament yang lebih modern
                            ->helperText('Pilih status terkini dari meja ini.'),

                        TextInput::make('kapasitas')
                            ->label('Kapasitas (Orang)')
                            ->numeric()
                            ->minValue(1) // Kapasitas minimal 1 orang
                            ->nullable() // Kapasitas bisa kosong jika tidak diketahui
                            ->placeholder('Contoh: 4')
                            ->helperText('Jumlah maksimum orang yang dapat ditampung meja ini.'),
                    ]),
            ]);
    }

    public static function table(Tables\Table $table): Tables\Table
    {
        return $table
            ->columns([
                TextColumn::make("No")->rowIndex(),
                Tables\Columns\TextColumn::make('nama')
                    ->label('Nomor / Nama Meja')
                    ->searchable()
                    ->wrap()
                    ->sortable(),
                SelectColumn::make('status')
                    ->label('Status')
                    ->options([
                        'tersedia' => 'Tersedia',
                        'dipakai' => 'Dipakai',
                    ])
                    ->sortable(),
                Tables\Columns\TextColumn::make('kapasitas')
                    ->label('Kapasitas')
                    ->suffix(' orang')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: false),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Dibuat Pada')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('updated_at')
                    ->label('Diperbarui Pada')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label('Filter Status')
                    ->options([
                        'tersedia' => 'Tersedia',
                        'dipakai' => 'Dipakai',
                    ]),
                Tables\Filters\Filter::make('kapasitas')
                    ->label('Filter Kapasitas')
                    ->form([
                        TextInput::make('min_kapasitas')
                            ->label('Min. Kapasitas')
                            ->numeric()
                            ->placeholder('Min'),
                        TextInput::make('max_kapasitas')
                            ->label('Max. Kapasitas')
                            ->numeric()
                            ->placeholder('Max'),
                    ])
                    ->query(function (\Illuminate\Database\Eloquent\Builder $query, array $data): \Illuminate\Database\Eloquent\Builder {
                        return $query
                            ->when(
                                $data['min_kapasitas'],
                                fn(\Illuminate\Database\Eloquent\Builder $query, $value): \Illuminate\Database\Eloquent\Builder => $query->where('kapasitas', '>=', $value),
                            )
                            ->when(
                                $data['max_kapasitas'],
                                fn(\Illuminate\Database\Eloquent\Builder $query, $value): \Illuminate\Database\Eloquent\Builder => $query->where('kapasitas', '<=', $value),
                            );
                    }),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                ComponentsSection::make('Detail Informasi Meja')
                    ->description('Rincian lengkap tentang meja ini.')
                    ->columns(2)
                    ->schema([
                        Group::make()
                            ->schema([
                                TextEntry::make('nama')
                                    ->label('Nomor / Nama Meja'),
                                TextEntry::make('status')
                                    ->label('Status Meja')
                                    ->badge()
                                    ->icon(fn(string $state): string => match ($state) {
                                        'tersedia' => 'heroicon-o-check-badge',
                                        'dipakai' => 'heroicon-o-x-circle',
                                        default => 'heroicon-o-question-mark-circle',
                                    })
                                    ->color(fn(string $state): string => match ($state) {
                                        'tersedia' => 'success',
                                        'dipakai' => 'danger',
                                        default => 'gray',
                                    }),
                            ]),
                        Group::make()
                            ->schema([
                                TextEntry::make('kapasitas')
                                    ->label('Kapasitas')
                                    ->suffix(' orang')
                                    ->icon('heroicon-o-users'),
                                TextEntry::make('created_at')
                                    ->label('Dibuat Pada')
                                    ->dateTime('d F Y, H:i:s')
                                    ->icon('heroicon-o-clock'),
                                TextEntry::make('updated_at')
                                    ->label('Diperbarui Pada')
                                    ->dateTime('d F Y, H:i:s')
                                    ->icon('heroicon-o-arrow-path'),
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
            'index' => Pages\ListMejas::route('/'),
            'create' => Pages\CreateMeja::route('/create'),
            'edit' => Pages\EditMeja::route('/{record}/edit'),
        ];
    }
}
