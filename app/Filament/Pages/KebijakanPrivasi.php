<?php

namespace App\Filament\Pages;

use App\Models\KebijakanPrivasi as ModelsKebijakanPrivasi;
use Filament\Actions\Action;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

class KebijakanPrivasi extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-shield-check';

    protected static string $view = 'filament.pages.kebijakan-privasi';
    protected static ?string $navigationLabel = 'Kebijakan Privasi';
    protected static ?string $title = 'Pengaturan Kebijakan Privasi';
    protected static ?string $navigationGroup = 'Settings'; // Ubah grup navigasi
    protected static ?int $navigationSort = 97;

    // Properti untuk menampung data dari database
    public ?array $data = [];

    /**
     * Mount lifecycle hook.
     * Mengambil data dari database saat halaman dimuat.
     */
    public function mount(): void
    {
        $kebijakan = ModelsKebijakanPrivasi::firstOrCreate([]);
        $this->form->fill($kebijakan->toArray());
    }

    /**
     * Mendefinisikan skema form.
     */
    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Konten Kebijakan Privasi')
                    ->description('Edit konten yang akan ditampilkan di halaman kebijakan privasi publik.')
                    ->schema([
                        RichEditor::make('body')
                            ->label('Isi Kebijakan')
                            ->required()
                            ->columnSpanFull(),
                    ]),
            ])
            ->model(KebijakanPrivasi::class)
            ->statePath('data');
    }

    /**
     * Mendefinisikan aksi (tombol simpan).
     */
    protected function getFormActions(): array
    {
        return [
            Action::make('save')
                ->label('Simpan Perubahan')
                ->submit('save'),
        ];
    }

    /**
     * Logika untuk menyimpan data.
     */
    public function save(): void
    {
        try {
            $kebijakan = ModelsKebijakanPrivasi::first();
            $kebijakan->update($this->form->getState());

            Notification::make()
                ->title('Berhasil Disimpan')
                ->body('Konten kebijakan privasi telah diperbarui.')
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
