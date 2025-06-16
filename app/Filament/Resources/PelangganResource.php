<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PelangganResource\Pages;
use App\Filament\Resources\PelangganResource\RelationManagers;
use App\Models\User; // Menggunakan model User karena pelanggan adalah bagian dari tabel users
use Filament\Forms;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Facades\Hash; // Untuk hashing password
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str; // Untuk slug email

class PelangganResource extends Resource
{
    // Menggunakan model User
    protected static ?string $model = User::class;

    protected static ?string $navigationIcon = 'heroicon-o-user-group';
    protected static ?string $navigationGroup = 'Users';
    protected static ?string $navigationLabel = 'Pelanggan';
    protected static ?int $navigationSort = 7;

    // Menambahkan query scope untuk memastikan hanya menampilkan pengguna dengan role 'user'
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->where('role', 'user');
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Informasi Pelanggan')
                    ->description('Detail akun dan kontak pelanggan.')
                    ->schema([
                        TextInput::make('name')
                            ->label('Nama Pelanggan')
                            ->placeholder('Masukkan nama lengkap pelanggan')
                            ->required()
                            ->maxLength(255)
                            ->autofocus()
                            ->live(onBlur: true) // Aktifkan live untuk update email otomatis
                            ->afterStateUpdated(function (string $operation, $state, Forms\Set $set) {
                                // Hanya set email jika operasi adalah 'create'
                                if ($operation === 'create') {
                                    $set('email', Str::slug($state) . '@gmail.com');
                                }
                            }),

                        TextInput::make('email')
                            ->label('Email')
                            ->placeholder('Otomatis dari nama pelanggan')
                            ->email()
                            ->unique(ignoreRecord: true) // Pastikan email unik, abaikan record saat ini saat edit
                            ->required()
                            ->maxLength(255)
                            ->disabled() // Tidak bisa diedit langsung
                            ->dehydrated(), // Dehydrate agar nilainya disimpan ke DB

                        // Password field dengan default '123' untuk create
                        // dan opsional untuk edit
                        TextInput::make('password') // Menggunakan TextInput untuk kontrol lebih
                            ->label('Kata Sandi')
                            ->password() // Tipe input password
                            ->autocomplete('new-password')
                            ->revealable()
                            ->dehydrateStateUsing(fn(string $state): string => Hash::make($state)) // Hash password saat disimpan
                            ->dehydrated(fn(?string $state, string $operation): bool => filled($state) || $operation === 'create')
                            ->required(fn(string $operation): bool => $operation === 'create') // Wajib diisi saat create
                            ->default('123') // Default '123' untuk create
                            ->hint('Biarkan kosong jika tidak ingin mengubah kata sandi.'),

                        TextInput::make('phone')
                            ->label('Nomor Telepon')
                            ->tel()
                            ->maxLength(255)
                            ->nullable()
                            ->placeholder('Misalnya: 081234567890'),

                        Textarea::make('address')
                            ->label('Alamat')
                            ->maxLength(255)
                            ->nullable()
                            ->columnSpan(['lg' => 2])
                            ->placeholder('Alamat lengkap pelanggan'),

                        FileUpload::make('avatar')
                            ->label('Foto Profil')
                            ->image()
                            ->directory('avatars')
                            ->visibility('public')
                            ->nullable()
                            ->deletable(true)
                            ->columnSpan(['lg' => 2])
                            ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])
                            ->maxSize(2048) // Maksimal 2MB
                            ->hint('Unggah foto profil (JPG, PNG, WEBP, maks 2MB).'),

                        Toggle::make('is_active')
                            ->label('Status Akun Aktif')
                            ->default(true)
                            ->columnSpan(['lg' => 2])
                            ->inline(false)
                            ->helperText('Nonaktifkan akun pelanggan jika tidak diperlukan.'),

                        // Hidden field for role, always 'user' for this resource
                        Forms\Components\Hidden::make('role')
                            ->default('customer')
                            ->dehydrated(true),
                    ])->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\ImageColumn::make('avatar')
                    ->label('Foto')
                    ->square()
                    ->defaultImageUrl(url('/images/placeholder.svg')), // Placeholder jika tidak ada avatar

                Tables\Columns\TextColumn::make('name')
                    ->label('Nama Pelanggan')
                    ->searchable()
                    ->sortable()
                    ->wrap(),

                Tables\Columns\TextColumn::make('email')
                    ->label('Email')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('phone')
                    ->label('Telepon')
                    ->searchable()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: false),

                Tables\Columns\TextColumn::make('address')
                    ->label('Alamat')
                    ->searchable()
                    ->sortable()
                    ->wrap()
                    ->toggleable(isToggledHiddenByDefault: true), // Sembunyikan secara default

                Tables\Columns\ToggleColumn::make('is_active')
                    ->label('Status Aktif')
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
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Status Akun')
                    ->boolean()
                    ->trueLabel('Aktif')
                    ->falseLabel('Nonaktif')
                    ->placeholder('Semua')
                    ->attribute('is_active'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make()
                    ->after(function ($record) {
                        // Hapus file avatar dari storage jika ada
                        if ($record->avatar) {
                            Storage::disk('public')->delete($record->avatar);
                        }
                    }),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()
                        ->after(function ($records) {
                            foreach ($records as $record) {
                                // Hapus file avatar dari storage untuk setiap record yang dihapus
                                if ($record->avatar) {
                                    Storage::disk('public')->delete($record->avatar);
                                }
                            }
                        }),
                ]),
            ])
            ->emptyStateActions([
                Tables\Actions\CreateAction::make(),
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
            'index' => Pages\ListPelanggans::route('/'),
            'create' => Pages\CreatePelanggan::route('/create'),
            'edit' => Pages\EditPelanggan::route('/{record}/edit'),
        ];
    }
}
