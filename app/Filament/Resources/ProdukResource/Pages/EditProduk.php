<?php

namespace App\Filament\Resources\ProdukResource\Pages;

use App\Filament\Resources\ProdukResource;
use App\Models\BahanBaku;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Validation\ValidationException;

class EditProduk extends EditRecord
{
    protected static string $resource = ProdukResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }

    protected function afterSave(): void
    {
        $produk = $this->record->load('produkBahan');
        foreach ($produk->produkBahan as $item) {
            $bahan = BahanBaku::find($item->bahan_baku_id);

            if (!$bahan) {
                continue;
            }

            if ($bahan->stok < $item->qty) {
                throw ValidationException::withMessages([
                    'produkBahan' => "Stok bahan baku '{$bahan->bahan}' tidak cukup (tersisa: {$bahan->stok})"
                ]);
            }

            $bahan->stok -= $item->qty;
            $bahan->save();
        }
    }
}
