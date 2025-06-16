<?php

namespace App\Filament\Resources\TransaksiResource\Pages;

use App\Filament\Resources\TransaksiResource;
use Filament\Actions;
use Filament\Resources\Components\Tab;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Contracts\Support\Htmlable;

class ListTransaksis extends ListRecords
{
    protected static string $resource = TransaksiResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }

    public function getTitle(): string|Htmlable
    {
        return 'Transaksi';
    }

    protected function getHeaderWidgets(): array
    {
        return TransaksiResource::getWidgets();
    }

    public function getTabs(): array
    {
        return [
            null => Tab::make('Semua'),
            'pending' => Tab::make()->query(fn($query) => $query->where('status', 'pending')),
            'failed' => Tab::make()->query(fn($query) => $query->where('status', 'failed')),
            'paid' => Tab::make()->query(fn($query) => $query->where('status', 'paid')),
        ];
    }
}
