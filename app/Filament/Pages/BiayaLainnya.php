<?php

namespace App\Filament\Pages;

use App\Models\Setting;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Card;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\RawJs;

class BiayaLainnya extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-cog-6-tooth';
    protected static ?string $navigationLabel = 'Biaya Lainnya & PPN';
    protected static ?string $title = 'Pengaturan Biaya Lainnya';
    protected static ?string $navigationGroup = 'Settings'; // Ubah grup navigasi
    protected static ?int $navigationSort = 99;


    protected static string $view = 'filament.pages.biaya-lainnya';

    public ?array $data = [];
    public Setting $setting;


    public function mount(): void
    {
        $this->setting = Setting::first();
        $this->form->fill($this->setting->toArray());
    }

    public function form(Form $form): Form
    {
        return $form->schema([
            Section::make([
                Grid::make(2)->schema([
                    TextInput::make('ppn')
                        ->label('PPN (%)')
                        ->placeholder('Masukkan nilai PPN')
                        ->numeric()
                        ->minValue(0)
                        ->maxValue(100)
                        ->suffix('%'),

                    TextInput::make('biaya_lainnya')
                        ->label('Biaya Lainnya (Rp)')
                        ->placeholder('Masukkan nominal biaya lainnya')
                        ->prefix('Rp')
                        ->mask(RawJs::make('$money($input)'))
                        ->stripCharacters(',')
                        ->numeric()
                ]),
            ]),
        ])->statePath('data');
    }

    public function submit(): void
    {
        $data = $this->form->getState();
        $q = Setting::findOrFail(1);
        $q->update($data);

        Notification::make()
            ->title('Pengaturan berhasil disimpan.')
            ->success()
            ->send();
    }
}
