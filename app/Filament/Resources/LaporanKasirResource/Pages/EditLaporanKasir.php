<?php

namespace App\Filament\Resources\LaporanKasirResource\Pages;

use App\Filament\Resources\LaporanKasirResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditLaporanKasir extends EditRecord
{
    protected static string $resource = LaporanKasirResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
