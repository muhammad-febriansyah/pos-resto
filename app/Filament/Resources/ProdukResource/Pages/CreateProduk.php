<?php

namespace App\Filament\Resources\ProdukResource\Pages;

use App\Filament\Resources\ProdukResource;
use App\Models\BahanBaku;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class CreateProduk extends CreateRecord
{
    protected static string $resource = ProdukResource::class;

    protected function afterCreate(): void
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
