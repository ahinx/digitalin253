<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;
use Filament\Forms\Form;
use Filament\Forms\Components\Tabs;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\FileUpload;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Log;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

class ManageAppSettings extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-cog';
    protected static ?string $navigationGroup = 'Pengaturan';
    protected static string $view = 'filament.pages.manage-app-settings';
    protected static ?string $navigationLabel = 'Pengaturan Aplikasi';

    public ?array $data = [];
    public $app_logo;
    public $app_image;

    public static function shouldRegisterNavigation(): bool
    {
        return true;
    }

    public function mount(): void
    {
        $this->form->fill(appSettings());
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Tabs::make('Pengaturan')
                    ->tabs([
                        Tabs\Tab::make('Midtrans Settings')->schema([
                            TextInput::make('midtrans_client_key'),
                            TextInput::make('midtrans_server_key'),
                            Select::make('midtrans_mode')
                                ->options([
                                    'sandbox' => 'Sandbox',
                                    'production' => 'Production',
                                ]),
                        ])->columns(2),

                        Tabs\Tab::make('Aplikasi')->schema([
                            TextInput::make('app_name_admin')->label('Nama Aplikasi Admin'),
                            TextInput::make('app_name_public')->label('Nama Aplikasi Pembeli'),
                            FileUpload::make('app_logo')
                                ->label('Gambar Logo Admin')
                                ->disk('public') // ✅ Penting: gunakan disk 'public'
                                ->directory('assetAplikasi') // ✅ Folder tujuan di storage/app/public
                                ->image()
                                ->imageEditor()
                                ->imageEditorAspectRatios([
                                    null,
                                    '16:9',
                                    '4:3',
                                    '1:1',
                                ])
                                ->maxFiles(1)
                                ->preserveFilenames(),

                            FileUpload::make('app_image')
                                ->label('Gambar Logo Halaman Utama')
                                ->disk('public') // ✅ Penting: gunakan disk 'public'
                                ->directory('assetAplikasi') // ✅ Folder tujuan di storage/app/public
                                ->image()
                                ->imageEditor()
                                ->imageEditorAspectRatios([
                                    null,
                                    '16:9',
                                    '4:3',
                                    '1:1',
                                ])
                                ->maxFiles(1)
                                ->preserveFilenames(),

                        ])->columns(2),

                        Tabs\Tab::make('WhatsApp Gateway')->schema([
                            TextInput::make('whatsapp_api_url'),
                            TextInput::make('whatsapp_api_token'),
                        ])->columns(2),
                    ])
            ])
            ->statePath('data');
    }

    // TOMBOL AKSI
    protected function getHeaderActions(): array
    {
        return [
            Action::make('saveAll')
                ->label('Simpan Pengaturan')
                ->action(fn() => $this->saveAll()),
        ];
    }


    public function saveAll(): void
    {
        $formData = $this->form->getState(); // Ambil semua data dari semua tab

        Log::info('📦 Simpan Semua:', $formData);

        settings([
            // Midtrans
            'midtrans_client_key' => $formData['midtrans_client_key'] ?? '',
            'midtrans_server_key' => $formData['midtrans_server_key'] ?? '',
            'midtrans_mode' => $formData['midtrans_mode'] ?? '',

            // Aplikasi
            'app_name_admin' => $formData['app_name_admin'] ?? '',
            'app_name_public' => $formData['app_name_public'] ?? '',
            'app_logo' => $formData['app_logo'] ?? '',
            'app_image' => $formData['app_image'] ?? '',

            // WhatsApp Gateway
            'whatsapp_api_url' => $formData['whatsapp_api_url'] ?? '',
            'whatsapp_api_token' => $formData['whatsapp_api_token'] ?? '',
        ]);

        Notification::make()
            ->title('Semua pengaturan berhasil disimpan.')
            ->success()
            ->send();
    }
}
