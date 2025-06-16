<?php

namespace App\Filament\Resources\KategoriCatatanResource\Pages;

use App\Filament\Resources\KategoriCatatanResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListKategoriCatatans extends ListRecords
{
    protected static string $resource = KategoriCatatanResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
