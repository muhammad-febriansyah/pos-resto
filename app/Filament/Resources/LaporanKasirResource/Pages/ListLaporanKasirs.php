<?php

namespace App\Filament\Resources\LaporanKasirResource\Pages;

use App\Filament\Resources\LaporanKasirResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Contracts\Support\Htmlable;

class ListLaporanKasirs extends ListRecords
{
    protected static string $resource = LaporanKasirResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }

    public function getTitle(): string|Htmlable
    {
        return 'Laporan Kasir';
    }
}
