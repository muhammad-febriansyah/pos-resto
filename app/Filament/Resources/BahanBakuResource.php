<?php

namespace App\Filament\Resources;

use App\Filament\Resources\BahanBakuResource\Pages;
use App\Filament\Resources\BahanBakuResource\RelationManagers;
use App\Models\BahanBaku;
use App\Models\Satuan;
use Filament\Forms;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Filament\Support\RawJs; // Don't forget to import RawJs
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\TextInputColumn;

class BahanBakuResource extends Resource
{
    protected static ?string $model = BahanBaku::class;

    protected static ?string $navigationIcon = 'heroicon-o-circle-stack';
    protected static ?string $navigationGroup = 'Master Data';
    protected static ?string $navigationLabel = 'Bahan Baku';
    protected static ?int $navigationSort = 5;


    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Detail Bahan Baku')
                    ->description('Informasi lengkap mengenai bahan baku, termasuk nama, satuan, biaya, harga jual, dan stok.')
                    ->schema([
                        TextInput::make('bahan')
                            ->label('Nama Bahan Baku')
                            ->placeholder('Contoh: Tepung Terigu Protein Tinggi')
                            ->required()
                            ->maxLength(255)
                            ->autofocus()
                            ->columnSpanFull(),

                        Forms\Components\Select::make('satuan_id')
                            ->label('Satuan Unit')
                            ->options(
                                Satuan::all()->pluck('satuan', 'id')
                            )
                            ->required()
                            ->searchable()
                            ->preload()
                            ->placeholder('Pilih satuan unit (misalnya: kg, liter, pcs)'),

                        TextInput::make('biaya')
                            ->label('Biaya Pembelian per Unit')
                            ->required()
                            ->numeric()
                            ->default(0)
                            ->prefix('Rp')
                            ->mask(RawJs::make('$money($input)')) // Using RawJs for currency mask
                            ->stripCharacters(',') // Crucial for storing clean numeric data
                            ->placeholder('Masukkan biaya pembelian per unit')
                            ->minValue(0)
                            ->maxLength(20),

                        TextInput::make('harga')
                            ->label('Harga Jual per Unit')
                            ->required()
                            ->numeric()
                            ->default(0)
                            ->prefix('Rp')
                            ->mask(RawJs::make('$money($input)')) // Using RawJs for currency mask
                            ->stripCharacters(',') // Crucial for storing clean numeric data
                            ->placeholder('Masukkan harga jual per unit')
                            ->minValue(0)
                            ->maxLength(20),

                        TextInput::make('stok')
                            ->label('Jumlah Stok Saat Ini')
                            ->required()
                            ->numeric()
                            ->default(0)
                            ->placeholder('Masukkan jumlah stok bahan baku saat ini')
                            ->minValue(0)
                            ->maxLength(20),
                    ])
                    ->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make("No")->rowIndex(),
                TextColumn::make('bahan')
                    ->label('Bahan Baku')
                    ->searchable()
                    ->sortable()
                    ->wrap(),
                TextColumn::make('satuan.satuan')
                    ->label('Satuan Unit')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('biaya')
                    ->label('Biaya Pembelian')
                    ->formatStateUsing(fn($state) => 'Rp ' . number_format($state))
                    ->sortable(),
                TextColumn::make('harga')
                    ->label('Harga Jual')
                    ->formatStateUsing(fn($state) => 'Rp ' . number_format($state))
                    ->sortable(),
                TextInputColumn::make('stok')
                    ->type('number')
                    ->sortable()
                    ->searchable()
                    ->label('Stok'),
            ])
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
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
            'index' => Pages\ListBahanBakus::route('/'),
            'create' => Pages\CreateBahanBaku::route('/create'),
            'edit' => Pages\EditBahanBaku::route('/{record}/edit'),
        ];
    }
}
