<?php

namespace App\Filament\Resources;

use App\Filament\Resources\OrderResource\Pages;
use App\Jobs\SendWhatsAppNotification;
use App\Models\Order;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\ToggleButtons;
use Filament\Forms\Components\Actions\Action as FormAction;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Filament\Tables\Actions\Action as TableAction;
use Filament\Tables\Actions\ActionGroup;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Actions\ViewAction;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;

class OrderResource extends Resource
{
    protected static ?string $model = Order::class;
    protected static ?string $navigationIcon = 'heroicon-o-shopping-cart';
    protected static ?string $navigationGroup = 'Manajemen Toko';
    protected static ?int $navigationSort = 2;

    // KODE ANDA DI BAGIAN FORM INI TIDAK SAYA UBAH SAMA SEKALI
    public static function form(Form $form): Form
    {
        $record = $form->getRecord();
        $orderId = $record ? $record->id : 'BARU';

        return $form
            ->schema([
                Section::make()
                    ->schema([
                        Grid::make(2)->schema([
                            Placeholder::make('invoice_title')
                                ->label(false)
                                ->content(fn() => new HtmlString("<h3 class='text-lg font-semibold leading-6 text-gray-950 dark:text-white'>DETAIL INVOICE PESANAN #{$orderId}</h3><p class='text-sm text-gray-500 dark:text-gray-400'>Informasi lengkap pesanan dan statusnya.</p>")),

                            Placeholder::make('header_badges')
                                ->label(false)
                                ->content(function (?Order $record): HtmlString {
                                    if (!$record) {
                                        return new HtmlString('');
                                    }

                                    $date = $record->created_at->format('d M Y H:i');
                                    $status = $record->status;
                                    $statusText = Str::title($status);
                                    $statusColor = match ($status) {
                                        'paid' => 'success',
                                        'pending' => 'warning',
                                        'expired', 'cancelled', 'denied' => 'danger',
                                        'challenge' => 'info',
                                        default => 'gray',
                                    };
                                    $voucherCode = $record->voucher?->code ? Str::upper($record->voucher->code) : null;

                                    $html = Blade::render('
                                        <div class="flex items-center justify-end h-full gap-2">
                                            <x-filament::badge color="gray">{{ $date }}</x-filament::badge>
                                            @if($voucherCode)
                                                <x-filament::badge color="info">{{ $voucherCode }}</x-filament::badge>
                                            @endif
                                            <x-filament::badge :color="$statusColor">{{ $statusText }}</x-filament::badge>
                                        </div>
                                    ', [
                                        'date' => $date,
                                        'voucherCode' => $voucherCode,
                                        'statusColor' => $statusColor,
                                        'statusText' => $statusText,
                                    ]);

                                    return new HtmlString($html);
                                }),
                        ]),

                        Grid::make(2)->schema([
                            Placeholder::make('buyer_info')
                                ->label('INFORMASI PEMBELI')
                                ->content(fn(Order $record) => new HtmlString(Str::markdown(
                                    '**Nama:** ' . $record->buyer_name . '<br>' .
                                        '**Email:** ' . $record->email . '<br>' .
                                        '**Telepon:** ' . $record->phone
                                ))),

                            Placeholder::make('order_summary')
                                ->label('RINGKASAN BIAYA')
                                ->content(function (Order $record): HtmlString {
                                    $subtotal = collect($record->items)->sum(fn($item) => $item['price'] * $item['quantity']);
                                    $discount = $record->discount_amount;
                                    $total = $record->total_price;
                                    $summaryText =
                                        '**Total Pesanan:** ' . 'Rp' . number_format($subtotal, 0, ',', '.') . '<br>' .
                                        '**Diskon:** ' . '- Rp' . number_format($discount, 0, ',', '.') . '<br>' .
                                        '**Total Pembayaran:** ' . 'Rp' . number_format($total, 0, ',', '.');
                                    return new HtmlString(Str::markdown($summaryText));
                                }),
                        ]),

                        ToggleButtons::make('status')
                            ->label('UBAH STATUS PESANAN')
                            ->inline()
                            ->options(['pending' => 'Pending', 'paid' => 'Paid', 'expired' => 'Expired', 'cancelled' => 'Cancelled', 'denied' => 'Denied', 'challenge' => 'Challenge'])
                            ->colors(['pending' => 'gray', 'paid' => 'success', 'expired' => 'warning', 'cancelled' => 'danger', 'denied' => 'danger', 'challenge' => 'info'])
                            ->required()
                            ->visible(fn(string $operation): bool => $operation === 'edit'),

                        Section::make('ITEM PESANAN')
                            ->collapsible()
                            ->collapsed()
                            ->schema([
                                Repeater::make('items')
                                    ->relationship()
                                    ->disabled()
                                    ->label(false)
                                    ->columns(4)
                                    ->schema([
                                        Placeholder::make('product_name')->label('Produk')->content(fn($record) => $record->product->name . ($record->variant ? ' - ' . $record->variant->name : '')),
                                        Placeholder::make('quantity')->label('Kuantitas')->content(fn($record) => new HtmlString("<span class='fi-badge fi-color-gray'>x{$record->quantity}</span>")),
                                        Placeholder::make('price')->label('Harga Satuan')->content(fn($record) => 'Rp' . number_format($record->price, 0, ',', '.')),
                                        Placeholder::make('subtotal')->label('Subtotal')->content(fn($record) => 'Rp' . number_format($record->price * $record->quantity, 0, ',', '.')),
                                    ]),
                            ]),

                        Section::make('KUNCI AKSES PESANAN')
                            ->collapsible()
                            ->collapsed()
                            ->schema([
                                TextInput::make('magic_link_display')
                                    ->label('MAGIC LINK UNDUHAN')
                                    ->formatStateUsing(fn(Order $record) => $record->magic_link_token ? url('/magic-link/' . $record->magic_link_token) : 'Tidak Tersedia')
                                    ->disabled()
                                    ->suffixAction(
                                        FormAction::make('copy_link')
                                            ->icon('heroicon-o-clipboard')
                                            ->color('gray')
                                            ->extraAttributes([
                                                'x-on:click.prevent' => new HtmlString("
                                                    const textToCopy = \$el.closest('.fi-fo-text-input').querySelector('input').value;
                                                    navigator.clipboard.writeText(textToCopy)
                                                        .then(() => {
                                                            new FilamentNotification().title('Link berhasil disalin').success().send();
                                                        })
                                                        .catch(() => {
                                                            new FilamentNotification().title('Gagal menyalin link').danger().send();
                                                        });
                                                "),
                                                'title' => 'Salin Link',
                                            ])
                                    ),
                                TextInput::make('tracking_key_display')
                                    ->label('KUNCI PELACAKAN')
                                    ->formatStateUsing(fn(Order $record) => $record->tracking_key ?? 'N/A')
                                    ->disabled()
                                    ->suffixAction(
                                        FormAction::make('copy_key')
                                            ->icon('heroicon-o-clipboard')
                                            ->color('gray')
                                            ->extraAttributes([
                                                'x-on:click.prevent' => new HtmlString("
                                                    const textToCopy = \$el.closest('.fi-fo-text-input').querySelector('input').value;
                                                    navigator.clipboard.writeText(textToCopy)
                                                        .then(() => {
                                                            new FilamentNotification().title('Kunci berhasil disalin').success().send();
                                                        })
                                                        .catch(() => {
                                                            new FilamentNotification().title('Gagal menyalin kunci').danger().send();
                                                        });
                                                "),
                                                'title' => 'Salin Kunci',
                                            ])
                                    ),
                            ]),

                        Section::make('INFO PEMBAYARAN (midtrans)')
                            ->collapsible()->collapsed()->visible(fn(Order $record) => !empty($record->payment_info))
                            ->schema([
                                Textarea::make('payment_info')->label('DATA PEMBAYARAN MENTAH')->rows(10)->disabled()->dehydrated(false)
                                    ->formatStateUsing(function ($state): ?string {
                                        if (is_string($state)) {
                                            $state = json_decode($state, true);
                                        }
                                        return $state ? json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) : null;
                                    })
                            ]),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            // ->recordUrl(fn(Order $record): string => self::getUrl('view', ['record' => $record]))
            ->columns([
                TextColumn::make('No')->rowIndex(),
                TextColumn::make('created_at')->label('Tanggal Order')->dateTime('d M Y H:i')->sortable(),
                TextColumn::make('buyer_name')
                    ->label('Pembeli')
                    ->searchable(['buyer_name', 'email', 'phone']),
                TextColumn::make('total_price')
                    ->label('Total')
                    ->money('IDR')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('status')->badge()->color(fn(string $state): string => match ($state) {
                    'pending' => 'gray',
                    'paid' => 'success',
                    'expired' => 'warning',
                    'cancelled' => 'danger',
                    'denied' => 'danger',
                    'challenge' => 'info',
                    default => 'gray',
                })->sortable(),
                TextColumn::make('magic_link_token')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('tracking_key')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('voucher.code')
                    ->label('Voucher')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),

                // [TAMBAHAN] Memungkinkan pencarian di dalam data mentah Midtrans
                TextColumn::make('payment_info')
                    ->label('Info Pembayaran')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')->options(['pending' => 'Pending', 'paid' => 'Paid', 'expired' => 'Expired', 'cancelled' => 'Cancelled', 'denied' => 'Denied', 'challenge' => 'Challenge']),
            ])
            ->actions([
                ActionGroup::make([
                    ViewAction::make(),
                    EditAction::make(),
                    TableAction::make('send_whatsapp_reminder')
                        ->label('Kirim Ulang WA Pengingat')
                        ->icon('heroicon-o-bell-alert')
                        ->color('info')
                        ->action(function (Order $record) {
                            SendWhatsAppNotification::dispatch($record, 'payment_reminder');
                            Notification::make()->title('Pesan WA Pengingat Terkirim!')->body('Pesan pengingat pembayaran untuk ' . $record->buyer_name . ' telah dikirim.')->success()->send();
                        })
                        ->visible(fn(Order $record): bool => $record->status === 'pending'),
                    TableAction::make('send_whatsapp_success')
                        ->label('Kirim Ulang WA Sukses')
                        ->icon('heroicon-o-check-circle')
                        ->color('success')
                        ->action(function (Order $record) {
                            SendWhatsAppNotification::dispatch($record, 'payment_success');
                            Notification::make()->title('Pesan WA Sukses Terkirim!')->body('Notifikasi pembayaran sukses untuk ' . $record->buyer_name . ' telah dikirim.')->success()->send();
                        })
                        ->visible(fn(Order $record): bool => $record->status === 'paid'),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListOrders::route('/'),
            // 'edit' => Pages\EditOrder:route('/{record}/edit'),
            // 'view' => Pages\ViewOrder::route('/{record}'),
        ];
    }

    public static function getWidgets(): array
    {
        return [];
    }

    public static function getGloballySearchableAttributes(): array
    {
        return [];
    }

    public static function getGlobalSearchResultDetails(Model $record): array
    {
        return [];
    }

    public static function getGlobalSearchResultUrl(Model $record): string
    {
        return '';
    }
}
