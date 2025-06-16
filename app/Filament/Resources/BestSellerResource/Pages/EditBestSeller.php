<?php

namespace App\Filament\Resources\BestSellerResource\Pages;

use App\Filament\Resources\BestSellerResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditBestSeller extends EditRecord
{
    protected static string $resource = BestSellerResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
