<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ProductResource\Pages;
use App\Models\Product;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Get;

class ProductResource extends Resource
{
    protected static ?string $model = Product::class;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Grid::make(2)->schema([
                    TextInput::make('name')->required(),
                    Select::make('type')
                        ->options([
                            'simple' => 'Simple',
                            'variant' => 'Variant'
                        ])->required()
                        ->reactive(),
                ]),

                FileUpload::make('main_image')->directory('products/images')->image(),
                Textarea::make('description')->columnSpanFull(),

                TextInput::make('price')
                    ->numeric()
                    ->visible(fn(Get $get) => $get('type') === 'simple'),

                TextInput::make('discount_price')
                    ->numeric()
                    ->visible(fn(Get $get) => $get('type') === 'simple'),

                Radio::make('downloadable_type')
                    ->options([
                        'file' => 'File',
                        'link' => 'Link'
                    ])
                    ->inline()
                    ->required()
                    ->reactive()
                    ->visible(fn(Get $get) => $get('type') === 'simple'),

                FileUpload::make('file_path')
                    ->directory('downloads')
                    ->label('Upload File')
                    ->preserveFilenames()
                    ->visible(fn(Get $get) => $get('type') === 'simple' && $get('downloadable_type') === 'file'),

                TextInput::make('external_url')
                    ->label('External Link')
                    ->visible(fn(Get $get) => $get('type') === 'simple' && $get('downloadable_type') === 'link'),

                Repeater::make('variants')
                    ->relationship('variants')
                    ->visible(fn(Get $get) => $get('type') === 'variant')
                    ->schema([
                        TextInput::make('name')->required(),
                        TextInput::make('price')->numeric()->required(),
                        FileUpload::make('image')->directory('products/variants')->image(),
                        Radio::make('downloadable_type')
                            ->options([
                                'file' => 'File',
                                'link' => 'Link'
                            ])->inline()->required()->reactive(),
                        FileUpload::make('file_path')
                            ->directory('downloads')
                            ->label('Upload File')
                            ->preserveFilenames()
                            ->visible(fn(Get $get) => $get('downloadable_type') === 'file'),
                        TextInput::make('external_url')
                            ->label('External Link')
                            ->visible(fn(Get $get) => $get('downloadable_type') === 'link'),
                    ])
                    ->defaultItems(1),

                TextInput::make('seo_title')->columnSpanFull(),
                Textarea::make('seo_description')->columnSpanFull(),
                TextInput::make('seo_keywords')->columnSpanFull(),
                TextInput::make('seo_image_alt')->columnSpanFull(),
            ]);
    }

    public static function table(Tables\Table $table): Tables\Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')->searchable(),
                Tables\Columns\TextColumn::make('type'),
                Tables\Columns\TextColumn::make('price')->money('IDR'),
                Tables\Columns\TextColumn::make('created_at')->dateTime(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListProducts::route('/'),
            'create' => Pages\CreateProduct::route('/create'),
            'edit' => Pages\EditProduct::route('/{record}/edit'),
        ];
    }
}
