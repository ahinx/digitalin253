<?php

namespace App\Filament\Resources\ProductResource\Pages;

use App\Filament\Resources\ProductResource;
use App\Models\Product;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CreateProduct extends CreateRecord
{
    protected static string $resource = ProductResource::class;

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

    protected function handleRecordCreation(array $data): Product
    {
        return DB::transaction(function () use ($data) {
            Log::info('Creating product with data:', $data);

            $product = Product::create([
                'name' => $data['name'],
                'type' => $data['type'],
                'main_image' => $data['main_image'] ?? null,
                'description' => $data['description'] ?? null,
                'price' => $data['price'] ?? null,
                'discount_price' => $data['discount_price'] ?? null,
                'seo_title' => $data['seo_title'] ?? null,
                'seo_description' => $data['seo_description'] ?? null,
                'seo_keywords' => $data['seo_keywords'] ?? null,
                'seo_image_alt' => $data['seo_image_alt'] ?? null,
                'downloadable_type' => $data['downloadable_type'] ?? null,
                'file_path' => $data['file_path'] ?? null,
                'external_url' => $data['external_url'] ?? null,
            ]);

            if ($data['type'] === 'variant' && isset($data['variants'])) {
                foreach ($data['variants'] as $variant) {
                    $product->variants()->create([
                        'name' => $variant['name'],
                        'price' => $variant['price'],
                        'image' => $variant['image'] ?? null,
                        'downloadable_type' => $variant['downloadable_type'] ?? null,
                        'file_path' => $variant['file_path'] ?? null,
                        'external_url' => $variant['external_url'] ?? null,
                    ]);
                }
            }

            Log::info('Product creation completed:', ['id' => $product->id]);

            return $product;
        });
    }
}
