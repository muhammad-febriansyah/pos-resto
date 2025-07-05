<?php

namespace App\Filament\Pages;

use App\Models\SyaratKetentuan as ModelsSyaratKetentuan;
use Filament\Actions\Action;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Section;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

class SyaratKetentuan extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-document-check'; // <-- Ikon yang relevan

    protected static string $view = 'filament.pages.syarat-ketentuan';
    protected static ?string $navigationLabel = 'Syarat & Ketentuan';
    protected static ?string $title = 'Pengaturan Syarat & Ketentuan';
    protected static ?string $navigationGroup = 'Settings'; // Ubah grup navigasi
    protected static ?int $navigationSort = 96;

    public ?array $data = [];

    public function mount(): void
    {
        // Ambil data pertama, atau buat baru jika belum ada.
        $syarat = ModelsSyaratKetentuan::firstOrCreate([]);
        $this->form->fill($syarat->toArray());
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Konten Syarat & Ketentuan')
                    ->description('Edit konten yang akan ditampilkan di halaman syarat & ketentuan publik.')
                    ->schema([
                        RichEditor::make('body')
                            ->label('Isi Dokumen')
                            ->required()
                            ->columnSpanFull(),
                    ]),
            ])
            ->model(SyaratKetentuan::class)
            ->statePath('data');
    }

    protected function getFormActions(): array
    {
        return [
            Action::make('save')
                ->label('Simpan Perubahan')
                ->submit('save'),
        ];
    }

    public function save(): void
    {
        try {
            $syarat = ModelsSyaratKetentuan::first();
            $syarat->update($this->form->getState());

            Notification::make()
                ->title('Berhasil Disimpan')
                ->body('Konten syarat & ketentuan telah diperbarui.')
                ->success()
                ->send();
        } catch (\Exception $e) {
            Notification::make()
                ->title('Gagal Menyimpan')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }
}
