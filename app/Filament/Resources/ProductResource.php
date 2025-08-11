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
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Section;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

class ProductResource extends Resource
{
    protected static ?string $model = Product::class;
    protected static ?string $navigationGroup = 'Manajemen Toko'; // Kelompokkan di navigasi admin

    protected static ?string $navigationIcon = 'heroicon-o-cube';
    protected static ?int $navigationSort = 1; // Urutan di dalam grup navigasi

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Informasi Umum')
                    ->description('Detail utama produk')
                    ->schema([
                        Grid::make(2)->schema([
                            TextInput::make('name')
                                ->label('Nama Produk')
                                ->required(),

                            Select::make('type')
                                ->options([
                                    'simple' => 'Simple',
                                    'variant' => 'Variant'
                                ])
                                ->required()
                                ->reactive(),
                        ]),

                        FileUpload::make('main_image')
                            ->label('Gambar Utama')
                            ->disk('public')
                            ->image()
                            ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])
                            ->imageEditor()
                            ->imageEditorAspectRatios([
                                null,
                                '16:9',
                                '4:3',
                                '1:1',
                            ])
                            ->preserveFilenames(false)
                            ->saveUploadedFileUsing(function (TemporaryUploadedFile $file, Set $set) {
                                $pair = storeAsWebpWithThumb($file, 'products/images');
                                $set('main_image_thumb', $pair['thumb']);   // simpan thumb ke hidden field
                                return $pair['large'];                      // WAJIB return string untuk FileUpload single
                            }),
                        Hidden::make('main_image_thumb'),

                        Textarea::make('description')
                            ->label('Deskripsi')
                            ->columnSpanFull(),
                    ]),

                Section::make('Harga')
                    ->description('Harga hanya untuk produk simple')
                    ->schema([
                        Grid::make(2)->schema([
                            TextInput::make('price')
                                ->label('Harga')
                                ->numeric()
                                ->visible(fn(Get $get) => $get('type') === 'simple'),

                            TextInput::make('discount_price')
                                ->label('Harga Diskon')
                                ->numeric()
                                ->visible(fn(Get $get) => $get('type') === 'simple'),
                        ]),
                    ]),

                Section::make('Download')
                    ->description('Opsi download produk digital (untuk simple product)')
                    ->schema([
                        Radio::make('downloadable_type')
                            ->label('Tipe Unduhan')
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
                    ]),

                Section::make('Varian Produk')
                    ->description('Hanya tampil jika produk bertipe variant')
                    ->schema([
                        Repeater::make('variants')
                            ->relationship('variants')
                            ->visible(fn(Get $get) => $get('type') === 'variant')
                            ->schema([
                                TextInput::make('name')->required(),
                                TextInput::make('price')->numeric()->required(),

                                FileUpload::make('image')
                                    ->label('Gambar Varian')
                                    ->image()
                                    ->disk('public')
                                    ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])
                                    ->imageEditor()
                                    ->imageEditorAspectRatios([
                                        null,
                                        '1:1',
                                        '4:3'
                                    ])
                                    ->saveUploadedFileUsing(function (TemporaryUploadedFile $file, Set $set) {
                                        $pair = storeAsWebpWithThumb($file, 'products/variants');
                                        $set('main_image_thumb', $pair['thumb']);   // simpan thumb ke hidden field
                                        return $pair['large'];                      // WAJIB return string untuk FileUpload single
                                    }),

                                Hidden::make('main_image_thumb'),

                                Radio::make('downloadable_type')
                                    ->options([
                                        'file' => 'File',
                                        'link' => 'Link'
                                    ])
                                    ->inline()
                                    ->required()
                                    ->reactive(),

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
                    ]),

                Section::make('SEO')
                    ->description('Optimasi mesin pencari (SEO)')
                    ->collapsible()
                    ->collapsed()
                    ->schema([
                        TextInput::make('seo_title')->columnSpanFull(),
                        Textarea::make('seo_description')->columnSpanFull(),
                        TextInput::make('seo_keywords')->columnSpanFull(),
                        TextInput::make('seo_image_alt')->columnSpanFull(),
                    ]),
            ]);
    }

    public static function table(Tables\Table $table): Tables\Table
    {
        return $table
            ->columns([
                Tables\Columns\ImageColumn::make('main_image')
                    ->label('Gambar')
                    ->circular()
                    ->height(50)
                    ->width(50),

                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('type')
                    ->label('Tipe')
                    ->sortable(),

                Tables\Columns\TextColumn::make('price')
                    ->label('Harga')
                    ->money('IDR')
                    ->sortable(),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Dibuat')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\TrashedFilter::make(), // Filter soft delete
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
                Tables\Actions\RestoreAction::make(), // Untuk soft delete
                Tables\Actions\ForceDeleteAction::make(), // Hapus permanen
            ])
            ->bulkActions([
                Tables\Actions\DeleteBulkAction::make(),
                Tables\Actions\RestoreBulkAction::make(),
                Tables\Actions\ForceDeleteBulkAction::make(),
            ])
            ->emptyStateActions([
                Tables\Actions\CreateAction::make(),
            ])
            ->defaultSort('created_at', 'desc')
            ->paginated(true)
            ->recordTitleAttribute('name')
            ->query(Product::query()->withTrashed()); // Aktifkan soft delete
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
