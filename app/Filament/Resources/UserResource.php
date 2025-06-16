<?php

namespace App\Filament\Resources;

use App\Filament\Resources\UserResource\Pages;
use App\Filament\Resources\UserResource\RelationManagers;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static ?string $navigationIcon = 'heroicon-o-users';
    protected static ?string $navigationGroup = 'Users';
    protected static ?string $navigationLabel = 'User';
    protected static ?int $navigationSort = 6;


    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Informasi Dasar') // Judul bagian form
                    ->description('Detail informasi pribadi pengguna.')
                    ->columns(2) // Atur layout menjadi 2 kolom
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->label('Nama Lengkap') // Label dalam bahasa Indonesia
                            ->required()
                            ->maxLength(255)
                            ->placeholder('Masukkan nama lengkap'), // Placeholder
                        Forms\Components\TextInput::make('email')
                            ->label('Email')
                            ->email() // Validasi format email
                            ->required()
                            ->maxLength(255)
                            ->unique(ignoreRecord: true) // Pastikan email unik, abaikan rekaman saat ini saat mengedit
                            ->placeholder('Masukkan alamat email'),
                    ]),

                Forms\Components\Section::make('Keamanan Akun')
                    ->description('Atur kata sandi untuk akun pengguna.')
                    ->columns(2)
                    ->schema([
                        Forms\Components\TextInput::make('password')
                            ->label('Kata Sandi')
                            ->password() // Jenis input password
                            ->dehydrateStateUsing(fn(string $state): string => Hash::make($state)) // Hash password saat disimpan
                            ->dehydrated(fn(?string $state): bool => filled($state)) // Hanya proses jika ada input password
                            ->required(fn(string $operation): bool => $operation === 'create') // Wajib diisi saat membuat, tidak wajib saat mengedit
                            ->rule(Password::default()) // Aturan default untuk kompleksitas password
                            ->autocomplete('new-password') // Nonaktifkan autofill password
                            ->placeholder('Masukkan kata sandi baru')
                            ->extraAttributes(['class' => 'font-mono']), // Gaya tambahan
                        Forms\Components\TextInput::make('password_confirmation') // Field konfirmasi password
                            ->label('Konfirmasi Kata Sandi')
                            ->password()
                            ->required(fn(string $operation): bool => $operation === 'create')
                            ->dehydrated(false) // Jangan simpan ke database
                            ->autocomplete('new-password')
                            ->placeholder('Ulangi kata sandi baru'),
                    ]),

                Forms\Components\Section::make('Detail Kontak & Alamat')
                    ->description('Informasi tambahan mengenai kontak dan lokasi pengguna.')
                    ->columns(1)
                    ->schema([
                        Forms\Components\TextInput::make('phone')
                            ->label('Nomor Telepon')
                            ->tel() // Validasi format telepon (opsional, tergantung kebutuhan)
                            ->maxLength(255)
                            ->nullable()
                            ->placeholder('Contoh: 081234567890'),
                        Forms\Components\Textarea::make('address')
                            ->label('Alamat')
                            ->maxLength(65535) // Ukuran maksimal untuk TEXT
                            ->nullable()
                            ->rows(3) // Jumlah baris yang terlihat
                            ->cols(10) // Jumlah kolom (opsional)
                            ->placeholder('Contoh: Jl. Merdeka No. 10, Jakarta Pusat'),
                    ]),

                Forms\Components\Section::make('Pengaturan Lainnya')
                    ->description('Pengaturan peran dan status akun pengguna.')
                    ->columns(1)
                    ->schema([

                        Forms\Components\Select::make('role')
                            ->label('Role')
                            ->options([
                                'admin' => 'Administrator',
                                'kasir' => 'Kasir', // Contoh peran tambahan
                            ])
                            ->required()
                            ->native(false), // Gunakan styling custom Filament untuk select
                        Forms\Components\Toggle::make('is_active')
                            ->label('Status Aktif')
                            ->default(true)
                            ->inline(false) // Tampilkan toggle di baris baru
                            ->onIcon('heroicon-s-check-circle') // Ikon saat aktif
                            ->offIcon('heroicon-s-x-circle') // Ikon saat tidak aktif
                            ->onColor('success') // Warna saat aktif
                            ->offColor('danger'), // Warna saat tidak aktif
                        Forms\Components\FileUpload::make('avatar')
                            ->label('Foto Profil')
                            ->image() // Hanya izinkan file gambar
                            ->directory('avatars') // Direktori penyimpanan di storage
                            ->nullable()
                            ->imageEditor() // Izinkan pengeditan gambar sederhana
                            ->imageCropAspectRatio('1:1'), // Aspek rasio untuk crop gambar
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\ImageColumn::make('avatar')
                    ->label('Avatar')
                    ->defaultImageUrl(url('/images/placeholder.svg')) // Opsional: Tambahkan placeholder

                    ->circular(), // Tampilkan gambar sebagai lingkaran
                Tables\Columns\TextColumn::make('name')
                    ->label('Nama')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('email')
                    ->label('Email')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('phone')
                    ->label('Telepon')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('role')
                    ->label('Peran')
                    ->badge() // Tampilkan sebagai badge
                    ->color(fn(string $state): string => match ($state) {
                        'admin' => 'info',
                        'kasir' => 'success',
                        default => 'gray',
                    }),
                Tables\Columns\IconColumn::make('is_active')
                    ->label('Aktif')
                    ->boolean(), // Tampilkan sebagai ikon boolean (centang/silang)
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Dibuat Pada')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true), // Sembunyikan secara default
                Tables\Columns\TextColumn::make('updated_at')
                    ->label('Diperbarui Pada')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                // Filter untuk peran
                Tables\Filters\SelectFilter::make('role')
                    ->label('Saring Berdasarkan Peran')
                    ->options([
                        'admin' => 'Administrator',
                        'kasir' => 'Kasir',
                    ]),
                // Filter untuk status aktif
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Status Aktif')
                    ->trueLabel('Aktif')
                    ->falseLabel('Tidak Aktif')
                    ->nullable(), // Izinkan filter untuk tidak aktifkan filter
            ])
            ->actions([
                Tables\Actions\EditAction::make(), // Aksi edit
                Tables\Actions\DeleteAction::make(), // Aksi hapus
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(), // Aksi hapus massal
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
            'index' => Pages\ListUsers::route('/'),
            'create' => Pages\CreateUser::route('/create'),
            'edit' => Pages\EditUser::route('/{record}/edit'),
        ];
    }
}
