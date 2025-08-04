<?php

namespace App\Filament\Resources\OrderResource\Pages;

use App\Filament\Resources\OrderResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Log;

class CreateOrder extends CreateRecord
{
    protected static string $resource = OrderResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        Log::info('Create Order - form data:', $data);
        return $data;
    }

    protected function afterCreate(): void
    {
        Log::info('Masuk ke afterCreate method');

        Log::info('Order berhasil dibuat:', [
            'order_id' => $this->record->id,
            'items' => $this->record->items()->get()->toArray(),
        ]);
    }
}
