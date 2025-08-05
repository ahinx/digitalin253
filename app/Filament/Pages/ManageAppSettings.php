<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;
use Filament\Forms\Form;
use Filament\Forms\Components\Tabs;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Actions;
use Filament\Forms\Components\Actions\Action;
use Filament\Actions\Action as HeaderAction;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;

class ManageAppSettings extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-cog';
    protected static ?string $navigationGroup = 'Pengaturan';
    protected static string $view = 'filament.pages.manage-app-settings';
    protected static ?string $navigationLabel = 'Pengaturan Aplikasi';

    public array $data = [];
    public ?string $midtransLog = null;
    public ?string $whatsappLog = null;

    public function mount(): void
    {
        $this->form->fill(appSettings());
    }

    protected function getHeaderActions(): array
    {
        return [
            HeaderAction::make('saveAll')
                ->label('Simpan Pengaturan')
                ->action('saveAll')
                ->color('primary')
                ->icon('heroicon-o-check-circle'),
        ];
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Tabs::make('Pengaturan')
                    ->tabs([

                        Tabs\Tab::make('Aplikasi')->schema([
                            TextInput::make('app_name_admin')->label('Nama Aplikasi Admin'),
                            TextInput::make('app_name_public')->label('Nama Aplikasi Pembeli'),
                            FileUpload::make('app_logo')
                                ->label('Gambar Logo Admin')
                                ->disk('public')
                                ->directory('assetAplikasi')
                                ->image()
                                ->imageEditor()
                                ->imageEditorAspectRatios([null, '16:9', '4:3', '1:1',])
                                ->maxFiles(1)
                                ->preserveFilenames(),
                            FileUpload::make('app_image')
                                ->label('Gambar Logo Halaman Utama')
                                ->disk('public')
                                ->directory('assetAplikasi')
                                ->image()
                                ->imageEditor()
                                ->imageEditorAspectRatios([null, '16:9', '4:3', '1:1',])
                                ->maxFiles(1)
                                ->preserveFilenames(),
                        ])->columns(2),


                        Tabs\Tab::make('Midtrans Settings')->schema([
                            TextInput::make('midtrans_client_key'),
                            TextInput::make('midtrans_server_key'),
                            Select::make('midtrans_mode')
                                ->options([
                                    'sandbox' => 'Sandbox',
                                    'production' => 'Production',
                                ]),
                            Section::make('Uji Coba Midtrans')
                                ->description('Kirim request ping ke Midtrans untuk menguji koneksi.')
                                ->schema([
                                    TextInput::make('test_order_id')
                                        ->label('Order ID Uji')
                                        ->helperText('Masukkan ID order uji, mis: ORDER-TEST-123'),
                                    TextInput::make('test_amount')
                                        ->label('Gross Amount Uji')
                                        ->helperText('Masukkan nominal, misal 10000'),
                                    Actions::make([
                                        Action::make('testMidtrans')
                                            ->label('Uji Koneksi Midtrans')
                                            ->action('sendTestMidtrans')
                                            ->color('warning')
                                            ->icon('heroicon-o-credit-card')
                                            ->requiresConfirmation()
                                            ->modalHeading('Uji Koneksi Midtrans')
                                            ->modalDescription('Akan mengirimkan request ping ke Midtrans.')
                                    ])->fullWidth()->columnSpanFull()->alignEnd(),
                                    Textarea::make('midtrans_log')
                                        ->label('Hasil Log Uji Coba')
                                        ->rows(5)
                                        ->readOnly()
                                        ->default('Log akan muncul di sini setelah uji coba...')
                                        ->formatStateUsing(fn() => $this->midtransLog)
                                        ->columnSpanFull(),
                                ])->columns(2),
                        ])->columns(2),



                        Tabs\Tab::make('WhatsApp Gateway')->schema([
                            TextInput::make('whatsapp_api_url')
                                ->label('API URL'),
                            TextInput::make('whatsapp_api_token')
                                ->label('API Token'),
                            Section::make('Uji Coba WhatsApp')
                                ->description('Kirim pesan uji coba ke nomor WhatsApp tertentu.')
                                ->schema([
                                    TextInput::make('test_phone')
                                        ->label('Nomor Telepon (contoh: 628123456789)')
                                        ->helperText('Masukkan nomor penerima untuk testing'),
                                    Textarea::make('test_message')
                                        ->label('Pesan Uji Coba')
                                        ->rows(3)
                                        ->default('🔔 Ini pesan uji coba dari Fonnte WhatsApp Gateway.')
                                        ->helperText('Isi pesan yang akan dikirim saat testing'),
                                    Actions::make([
                                        Action::make('testWhatsApp')
                                            ->label('Kirim Pesan Uji WA')
                                            ->action('sendTestWhatsApp')
                                            ->color('success')
                                            ->icon('heroicon-o-chat-bubble-left-right')
                                            ->requiresConfirmation()
                                            ->modalHeading('Kirim Pesan Uji WA')
                                            ->modalDescription('Pastikan nomor dan pesan sudah benar sebelum mengirim.')
                                            ->modalButton('Kirim'),
                                    ])->fullWidth()->columnSpanFull()->alignEnd(),
                                    Textarea::make('whatsapp_log')
                                        ->label('Hasil Log Uji Coba')
                                        ->rows(5)
                                        ->readOnly()
                                        ->default('Log akan muncul di sini setelah uji coba...')
                                        ->formatStateUsing(fn() => $this->whatsappLog)
                                        ->columnSpanFull(),
                                ])->columns(2),
                        ])->columns(2),
                    ]),
            ])
            ->statePath('data');
    }

    public function saveAll(): void
    {
        $formData = $this->form->getState();
        Log::info('📦 Simpan Semua Pengaturan:', $formData);
        settings([
            'midtrans_client_key' => $formData['midtrans_client_key'] ?? '',
            'midtrans_server_key' => $formData['midtrans_server_key'] ?? '',
            'midtrans_mode' => $formData['midtrans_mode'] ?? '',
            'app_name_admin' => $formData['app_name_admin'] ?? '',
            'app_name_public' => $formData['app_name_public'] ?? '',
            'app_logo' => $formData['app_logo'] ?? '',
            'app_image' => $formData['app_image'] ?? '',
            'whatsapp_api_url' => $formData['whatsapp_api_url'] ?? '',
            'whatsapp_api_token' => $formData['whatsapp_api_token'] ?? '',
        ]);

        Notification::make()
            ->title('Semua pengaturan berhasil disimpan.')
            ->success()
            ->send();
    }

    public function sendTestWhatsApp(): void
    {
        $form = $this->form->getState();
        $phone = $form['test_phone'] ?? null;
        $message = $form['test_message'] ?? null;
        $apiUrl = setting('whatsapp_api_url');
        $token = setting('whatsapp_api_token');

        if (!$phone || !$message) {
            $this->whatsappLog = 'Nomor dan pesan wajib diisi.';
            Notification::make()->title('Nomor dan pesan wajib diisi.')->danger()->send();
            return;
        }

        try {
            $response = Http::withHeaders([
                'Authorization' => $token,
            ])->post($apiUrl, [
                'target' => $phone,
                'message' => $message,
                'countryCode' => substr((string) $phone, 0, 2),
            ]);

            if ($response->successful()) {
                $this->whatsappLog = 'Pesan uji coba berhasil dikirim. Status: ' . $response->status();
                Notification::make()->title('Pesan uji coba berhasil dikirim.')->success()->send();
            } else {
                $this->whatsappLog = 'Gagal mengirim pesan. Status: ' . $response->status() . ' - Response: ' . $response->body();
                Notification::make()->title('Gagal mengirim pesan: ' . $response->status())->danger()->send();
            }
        } catch (\Exception $e) {
            Log::error('Error kirim test WA:', ['message' => $e->getMessage()]);
            $this->whatsappLog = 'Error: ' . $e->getMessage();
            Notification::make()->title('Error: ' . $e->getMessage())->danger()->send();
        }

        $this->form->fill($this->data);
    }

    public function sendTestMidtrans(): void
    {
        $form = $this->form->getState();
        $orderId = $form['test_order_id'] ?? null;
        $amount = $form['test_amount'] ?? null;

        if (!$orderId || !$amount) {
            $this->midtransLog = 'Order ID dan Amount wajib diisi.';
            Notification::make()->title('Order ID dan Amount wajib diisi.')->danger()->send();
            return;
        }

        $serverKey = setting('midtrans_server_key', config('services.midtrans.server_key'));
        $clientKey = setting('midtrans_client_key', config('services.midtrans.client_key'));

        if (!$serverKey || !$clientKey) {
            $this->midtransLog = 'Midtrans belum disetting.';
            Notification::make()->title('Midtrans belum disetting.')->danger()->send();
            return;
        }

        $mode = setting('midtrans_mode', config('services.midtrans.is_production') ? 'production' : 'sandbox');

        \Midtrans\Config::$serverKey = $serverKey;
        \Midtrans\Config::$clientKey = $clientKey;
        \Midtrans\Config::$isProduction = $mode === 'production';
        \Midtrans\Config::$isSanitized = true;
        \Midtrans\Config::$is3ds = true;

        try {
            $payload = [
                'transaction_details' => [
                    'order_id' => $orderId,
                    'gross_amount' => (float) $amount
                ],
                'customer_details' => [
                    'first_name' => 'Test',
                    'email' => 'test@example.com',
                    'phone' => '081234567890'
                ]
            ];
            $token = \Midtrans\Snap::getSnapToken($payload);
            $this->midtransLog = 'Test Midtrans berhasil. SnapToken: ' . $token;
            Notification::make()->title('Test Midtrans berhasil')->body('SnapToken: ' . $token)->success()->send();
        } catch (\Exception $e) {
            Log::error('Error test Midtrans:', ['message' => $e->getMessage()]);
            $this->midtransLog = 'Error test Midtrans: ' . $e->getMessage();
            Notification::make()->title('Error test Midtrans: ' . $e->getMessage())->danger()->send();
        }

        $this->form->fill($this->data);
    }
}
