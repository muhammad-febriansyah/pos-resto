<?php

namespace App\Filament\Resources\PelangganResource\Pages;

use App\Filament\Resources\PelangganResource;
use App\Models\User;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Builder;

class ListPelanggans extends ListRecords
{
    protected static string $resource = PelangganResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }

    protected function getTableQuery(): ?Builder
    {
        return User::query()
            ->where('role', 'pelanggan') // Filter untuk hanya menampilkan pelanggan
            ->withoutGlobalScopes(); // Menghindari scope global yang mungkin diterapkan pada model
    }

    public function getTitle(): string|Htmlable
    {
        return 'Data Pelanggan';
    }
}
