<?php

namespace App\Filament\Resources\ProductResource\Pages;

use App\Filament\Resources\ProductResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\Log;

class EditProduct extends EditRecord
{
    protected static string $resource = ProductResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        Log::info('Form data before create mutation:', $data);

        if (! empty($data['main_image_upload'])) {
            $data['main_image'] = [
                'large' => $data['main_image_upload'],
                'thumb' => $data['main_image_thumb'] ?? null,
            ];
        }
        unset($data['main_image_upload'], $data['main_image_thumb']);
        return $data;
    }
}
