<?php

namespace App\Filament\Pages;

use App\Models\Setting;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Section; // Import Section
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Forms\Form;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File; // Import the File facade
use Illuminate\Support\Facades\Storage;

class EditSettings extends Page implements HasForms
{
    use InteractsWithForms;
    public ?array $data = [];
    protected static ?string $navigationIcon = 'heroicon-o-cog';

    protected static string $view = 'filament.pages.edit-settings';

    protected static ?string $navigationGroup = 'Settings'; // Ubah grup navigasi
    protected static ?int $navigationSort = 100;
    protected static ?string $navigationLabel = 'Setting Website';


    public Setting $setting;

    /**
     * Mount the page and load the setting.
     * If no setting exist, create a new empty record.
     */
    public function mount(): void
    {
        $this->setting = Setting::first();
        $this->form->fill($this->setting->toArray());
    }


    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Informasi Umum Website') // Judul bagian
                    ->description('Kelola pengaturan dasar dan informasi kontak situs web Anda.') // Deskripsi bagian
                    ->schema([
                        TextInput::make('site_name')
                            ->label('Nama Situs') // Label dalam Bahasa Indonesia
                            ->placeholder('Masukkan nama situs Anda, contoh: Toko Online Saya') // Placeholder
                            ->required()
                            ->maxLength(255),
                        TextInput::make('keyword')
                            ->label('Kata Kunci') // Label dalam Bahasa Indonesia
                            ->placeholder('Masukkan kata kunci, pisahkan dengan koma') // Placeholder
                            ->maxLength(255),
                        TextInput::make('description')
                            ->label('Deskripsi') // Label dalam Bahasa Indonesia
                            ->placeholder('Masukkan deskripsi singkat situs Anda') // Placeholder
                            ->maxLength(255),
                        TextInput::make('email')
                            ->label('Email') // Label dalam Bahasa Indonesia
                            ->placeholder('Masukkan alamat email kontak, contoh: info@contoh.com') // Placeholder
                            ->email()
                            ->required()
                            ->maxLength(255),
                        TextInput::make('phone')
                            ->label('Nomor Telepon') // Label dalam Bahasa Indonesia
                            ->placeholder('Masukkan nomor telepon, contoh: +6281234567890') // Placeholder
                            ->tel()
                            ->maxLength(255),
                        TextInput::make('address')
                            ->label('Alamat') // Label dalam Bahasa Indonesia
                            ->placeholder('Masukkan alamat lengkap, contoh: Jl. Raya No. 123, Kota ABC') // Placeholder
                            ->maxLength(255),
                    ])->columns(2), // Opsional: Atur layout kolom dalam bagian ini

                Section::make('Tautan Media Sosial') // Judul bagian
                    ->description('Tambahkan URL profil media sosial Anda.') // Deskripsi bagian
                    ->schema([
                        TextInput::make('fb')
                            ->label('URL Facebook') // Label dalam Bahasa Indonesia
                            ->placeholder('Masukkan URL profil Facebook Anda') // Placeholder
                            ->url()
                            ->maxLength(255),
                        TextInput::make('ig')
                            ->label('URL Instagram') // Label dalam Bahasa Indonesia
                            ->placeholder('Masukkan URL profil Instagram Anda') // Placeholder
                            ->url()
                            ->maxLength(255),
                        TextInput::make('tiktok')
                            ->label('URL TikTok') // Label dalam Bahasa Indonesia
                            ->placeholder('Masukkan URL profil TikTok Anda') // Placeholder
                            ->url()
                            ->maxLength(255),
                    ])->columns(3), // Opsional: Atur layout kolom dalam bagian ini

                Section::make('Logo Website') // Judul bagian
                    ->description('Unggah logo situs web Anda.') // Deskripsi bagian
                    ->schema([
                        FileUpload::make('logo')
                            ->label('Logo Website') // Label dalam Bahasa Indonesia
                            ->disk('public')
                            ->directory('image-upload-server') // Direktori penyimpanan logo
                            ->maxSize(3072) // Max file size in KB (3MB)
                            ->image()
                            ->deletable(true) // Memungkinkan penghapusan file dari form
                            ->deleteUploadedFileUsing(function ($record, $file) {
                                if (isset($record->logo)) {
                                    if ($record->logo == $file->logo) {
                                        if (File::exists(public_path('storage\\' . $record->logo))) {
                                            File::delete(public_path('storage\\' . $record->logo));
                                        }
                                    }
                                }
                            })
                            ->columnSpan(['lg' => 2]), // Span 2 kolom pada layout large
                    ]),
                Section::make('Thumbnail') // Judul bagian
                    ->description('Unggah thumbnail situs web Anda.') // Deskripsi bagian
                    ->schema([
                        FileUpload::make('thumbnail')
                            ->label('Thumbnail') // Label dalam Bahasa Indonesia
                            ->disk('public')
                            ->directory('image-upload-server') // Direktori penyimpanan logo
                            ->maxSize(3072) // Max file size in KB (3MB)
                            ->image()
                            ->deletable(true) // Memungkinkan penghapusan file dari form
                            ->deleteUploadedFileUsing(function ($record, $file) {
                                if (isset($record->thumbnail)) {
                                    if ($record->thumbnail == $file->thumbnail) {
                                        if (File::exists(public_path('storage\\' . $record->thumbnail))) {
                                            File::delete(public_path('storage\\' . $record->thumbnail));
                                        }
                                    }
                                }
                            })
                            ->columnSpan(['lg' => 2]), // Span 2 kolom pada layout large
                    ]),

            ])
            ->statePath('data');
    }


    protected function getFormActions(): array
    {
        return [
            Action::make('save')
                ->label('Simpan Perubahan') // Label tombol dalam Bahasa Indonesia
                ->submit('save')
                ->color('primary'),
        ];
    }


    public function save()
    {
        try {
            $data = $this->form->getState();
            $q = Setting::findOrFail(1);
            if (isset($data['logo']) && $data['logo'] && $data['logo'] !== $q->logo) {
                $oldPath = str_replace('storage/', '', $q->logo);
                if (Storage::disk('public')->exists($oldPath)) {
                    Storage::disk('public')->delete($oldPath);
                }
            }
            if (isset($data['thumbnail']) && $data['thumbnail'] && $data['thumbnail'] !== $q->thumbnail) {
                $oldPath = str_replace('storage/', '', $q->thumbnail);
                if (Storage::disk('public')->exists($oldPath)) {
                    Storage::disk('public')->delete($oldPath);
                }
            }

            $q->update($data);

            Notification::make()
                ->success()
                ->title('Data berhasil disimpan')
                ->send();

            return $this->redirect('/admin/edit-settings', navigate: true);
        } catch (\Exception $exception) {
            Notification::make()
                ->warning()
                ->title($exception->getMessage())
                ->send();

            return $this->redirect('/admin/edit-settings', navigate: true);
        }
    }
}
