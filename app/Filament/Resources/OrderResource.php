<?php

namespace App\Filament\Resources;

use App\Models\Order;
use App\Models\Product;
use Filament\Resources\Resource;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Repeater;
use Illuminate\Support\Str;
use App\Filament\Resources\OrderResource\Pages;

class OrderResource extends Resource
{
    protected static ?string $model = Order::class;

    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-check';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                TextInput::make('buyer_name')->required(),
                TextInput::make('phone')->required(),
                TextInput::make('email')->email(),
                TextInput::make('magic_link_token')
                    ->default(fn() => Str::uuid()->toString())
                    ->disabled(),
                Select::make('status')
                    ->options([
                        'pending' => 'Pending',
                        'paid' => 'Paid',
                        'expired' => 'Expired',
                        'cancelled' => 'Cancelled',
                    ])
                    ->required(),

                Repeater::make('items')
                    ->relationship('items')
                    ->schema([
                        Select::make('product_id')
                            ->label('Product')
                            ->options(Product::query()->pluck('name', 'id'))
                            ->reactive()
                            ->required(),

                        Select::make('variant_id')
                            ->label('Variant')
                            ->options(function (callable $get) {
                                $product = Product::find($get('product_id'));
                                if (! $product || $product->type !== 'variant') {
                                    return [];
                                }
                                return $product->variants()->pluck('name', 'id');
                            })
                            ->visible(function (callable $get) {
                                $product = Product::find($get('product_id'));
                                return $product && $product->type === 'variant';
                            }),
                    ])
                    ->columns(1)
                    ->defaultItems(1),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('buyer_name')->searchable(),
                Tables\Columns\TextColumn::make('phone'),
                Tables\Columns\TextColumn::make('email')->sortable(),
                Tables\Columns\TextColumn::make('status')->badge()->color(fn($state) => match ($state) {
                    'pending' => 'gray',
                    'paid' => 'success',
                    'expired' => 'warning',
                    'cancelled' => 'danger',
                }),
                Tables\Columns\TextColumn::make('created_at')->dateTime()->sortable(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\DeleteBulkAction::make(),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListOrders::route('/'),
            'create' => Pages\CreateOrder::route('/create'),
            'edit' => Pages\EditOrder::route('/{record}/edit'),
        ];
    }
}
