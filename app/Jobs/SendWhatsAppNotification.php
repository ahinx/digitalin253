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
                $amount  = number_format((float) $this->order->total_price, 0, ',', '.'); // Pastikan casting ke float untuk total_price dari DB

                // Template pesan yang diperbaiki
                $message = "🔔 *PENGINGAT PEMBAYARAN*\n\n" // Icon dan judul tebal
                    . "Halo *{$this->order->buyer_name}*,\n"
                    . "Terima kasih telah memesan (Order #*{$this->order->id}*).\n\n" // Order ID tebal
                    . "Total pembayaran Anda adalah: *Rp{$amount}*.\n\n" // Total pembayaran tebal
                    . "Silakan selesaikan pembayaran Anda melalui tautan berikut:\n"
                    . "*{$paymentUrl}*\n\n" // Link tebal
                    . "Pesanan Anda akan diproses setelah pembayaran dikonfirmasi.\n"
                    . "Terima kasih!";
                break;

            case 'payment_success':
                // Pesan setelah pembayaran berhasil
                $orderDetailLink = url('/thank-you?order_id=' . $this->order->id);
                $downloadMagicLink = null;
                $trackingKey = $this->order->tracking_key; // <<< Pastikan ini mengambil tracking_key

                // Template pesan yang diperbaiki
                $message = "✅ *PEMBAYARAN BERHASIL*\n\n" // Icon dan judul tebal
                    . "Halo *{$this->order->buyer_name}*,\n"
                    . "Pembayaran untuk pesanan Anda (Order #*{$this->order->id}*) telah *berhasil diterima*! 🎉\n\n"; // Order ID dan status tebal

                if ($this->order->magic_link_token) {
                    $downloadMagicLink = url('/magic-link/' . $this->order->magic_link_token);
                    $message .= "Silakan unduh produk Anda di sini:\n"
                        . "*{$downloadMagicLink}*\n"; // Link tebal
                } else {
                    $message .= "Lihat detail pesanan Anda di sini:\n"
                        . "*{$orderDetailLink}*\n"; // Link tebal
                }

                // <<< Pastikan blok ini ada dan benar
                if ($trackingKey) {
                    $message .= "\nUntuk melacak pesanan Anda di masa mendatang, gunakan Kunci Pelacakan ini:\n"
                        . "*{$trackingKey}*\n"; // Kunci pelacakan tebal
                }
                $message .= "\nJika ada pertanyaan atau butuh bantuan, jangan ragu untuk menghubungi kami.\n"
                    . "Terima kasih atas pembelian Anda!";
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
