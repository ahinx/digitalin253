<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Models\Order; // Pastikan model Order di-import
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log; // Untuk logging error
use Illuminate\Support\Facades\URL; // Untuk signed routes

class SendWhatsAppNotification implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public Order $order; // Type-hinting untuk Order model
    public string $type; // Tipe notifikasi: 'payment_reminder', 'payment_success'

    /**
     * Create a new job instance.
     *
     * @param \App\Models\Order $order
     * @param string $type
     * @return void
     */
    public function __construct(Order $order, string $type)
    {
        $this->order = $order;
        $this->type = $type;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle(): void
    {
        // Ambil konfigurasi WhatsApp dari setting
        $apiUrl = setting('whatsapp_api_url');
        $token  = setting('whatsapp_api_token');
        $message = ''; // Variabel untuk menyimpan pesan WhatsApp

        // Pastikan API URL dan token tidak kosong
        if (empty($apiUrl) || empty($token)) {
            Log::error('WhatsApp API URL atau Token tidak dikonfigurasi. Notifikasi tidak terkirim.');
            return;
        }

        // Tentukan pesan berdasarkan tipe notifikasi
        switch ($this->type) {
            case 'payment_reminder':
                // Pesan untuk pengingat pembayaran (belum dibayar)
                // Menggunakan URL::signedRoute untuk keamanan link pembayaran
                $paymentUrl = URL::signedRoute('shop.paymentLink', ['order' => $this->order->id]);
                $amount  = number_format((float) $this->order->total_price, 0, ',', '.'); // <<< Pastikan casting ke float untuk total_price dari DB
                $message = "Halo {$this->order->buyer_name},\n"
                    . "Terima kasih telah memesan (Order #{$this->order->id}).\n"
                    . "Total pembayaran: Rp{$amount}.\n"
                    . "Silakan selesaikan pembayaran Anda di sini: {$paymentUrl}\n\n"
                    . "Terima kasih!";
                break;

            case 'payment_success':
                // PERBAIKAN: Gunakan url() helper, BUKAN route() helper
                $orderDetailLink = url('/thank-you?order_id=' . $this->order->id); // <<< PERBAIKAN INI PENTING

                if ($this->order->magic_link_token) {
                    // PERBAIKAN: Gunakan url() helper, BUKAN route() helper
                    $downloadMagicLink = url('/magic-link/' . $this->order->magic_link_token); // <<< PERBAIKAN INI PENTING
                    $message = "Halo {$this->order->buyer_name},\n"
                        . "Pembayaran untuk pesanan Anda (Order #{$this->order->id}) telah berhasil diterima. Terima kasih!\n"
                        . "Silakan unduh produk Anda di: {$downloadMagicLink}\n\n"
                        . "Jika ada pertanyaan, jangan ragu untuk menghubungi kami.";
                } else {
                    $message = "Halo {$this->order->buyer_name},\n"
                        . "Pembayaran untuk pesanan Anda (Order #{$this->order->id}) telah berhasil diterima. Terima kasih!\n"
                        . "Lihat detail pesanan Anda di sini: {$orderDetailLink}\n\n"
                        . "Kami akan segera memproses pesanan Anda.";
                }
                break;

            default:
                // Log peringatan jika tipe notifikasi tidak dikenali
                Log::warning('Tipe notifikasi WhatsApp tidak valid: ' . $this->type . ' untuk Order ID: ' . $this->order->id);
                return; // Hentikan eksekusi jika tipe tidak valid
        }

        // Kirim pesan ke Fonnte API jika pesan tidak kosong
        if (!empty($message)) {
            try {
                $response = Http::withHeaders([
                    'Authorization' => $token,
                ])->post($apiUrl, [
                    'target'      => $this->order->phone,
                    'message'     => $message,
                    'countryCode' => '62', // Pastikan format nomor telepon sesuai dengan kode negara
                ]);

                // Log respons Fonnte untuk debugging
                Log::info('WhatsApp notification sent for Order ID: ' . $this->order->id, [
                    'type' => $this->type,
                    'phone' => $this->order->phone,
                    'status' => $response->status(),
                    'response_body' => $response->json(),
                ]);
            } catch (\Exception $e) {
                // Tangani error pengiriman HTTP
                Log::error('Gagal mengirim pesan WhatsApp via Fonnte API untuk Order ID: ' . $this->order->id . '. Error: ' . $e->getMessage());
            }
        }
    }
}
