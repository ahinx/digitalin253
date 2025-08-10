<?php

namespace App\Filament\Resources\OrderResource\Pages;

use App\Filament\Resources\OrderResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditOrder extends EditRecord
{
    protected static string $resource = OrderResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(), // Tambahkan tombol View jika Anda punya halaman View terpisah
            Actions\DeleteAction::make(), // Biarkan Delete jika ingin admin bisa menghapus individual
        ];
    }
}
