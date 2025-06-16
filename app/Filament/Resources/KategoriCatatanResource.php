<?php

namespace App\Filament\Resources;

use App\Filament\Resources\KategoriCatatanResource\Pages;
use App\Filament\Resources\KategoriCatatanResource\RelationManagers;
use App\Models\KategoriCatatan;
use Filament\Forms;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\ToggleButtons;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class KategoriCatatanResource extends Resource
{
    protected static ?string $model = KategoriCatatan::class;

    protected static ?string $navigationIcon = 'heroicon-o-tag';
    protected static ?string $navigationGroup = 'Pencatatan';
    protected static ?string $navigationLabel = 'Kategori';
    protected static ?int $navigationSort = 8;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make()->schema([
                    TextInput::make('name')->required()->placeholder('Kategori Catatan')
                        ->label(' Kategori')
                        ->maxLength(255),
                    ToggleButtons::make('type')->inline()->options([
                        'pengeluaran' => 'Pengeluaran',
                        'pemasukan' => 'Pemasukan',
                    ])->required(),
                ])
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make("No")->rowIndex(),
                TextColumn::make('name')
                    ->label('Kategori')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('type')
                    ->label('Tipe')
                    ->badge()
                    ->searchable()
                    ->color(function ($state) {
                        return match ($state) {
                            'pengeluaran' => 'info',
                            'pemasukan' => 'success',
                        };
                    })
                    ->formatStateUsing(fn($state) => ucfirst($state))

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
            'index' => Pages\ListKategoriCatatans::route('/'),
            'create' => Pages\CreateKategoriCatatan::route('/create'),
            'edit' => Pages\EditKategoriCatatan::route('/{record}/edit'),
        ];
    }
}
