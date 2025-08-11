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
        Log::info('Form data before create mutation:', $data);

        // Jika file_path tidak ada, set null
        if (($data['downloadable_type'] ?? null) !== 'file') {
            $data['file_path'] = null;
        }
        if (($data['downloadable_type'] ?? null) !== 'link') {
            $data['external_url'] = null;
        }

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
