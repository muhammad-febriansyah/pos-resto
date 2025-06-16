<?php

namespace App\Filament\Resources\PelangganResource\Pages;

use App\Filament\Resources\PelangganResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Contracts\Support\Htmlable;

class CreatePelanggan extends CreateRecord
{
    protected static string $resource = PelangganResource::class;

    public function getTitle(): string|Htmlable
    {
        return 'Form Pelanggan';
    }
}
