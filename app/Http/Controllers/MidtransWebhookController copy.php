<?php

namespace App\Http\Controllers;

use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http; // Sudah ada
use Midtrans\Config as MidtransConfig; // Sudah ada
use Illuminate\Support\Facades\Log; // Sudah ada
use App\Jobs\SendWhatsAppNotification; // Import Job yang baru

class MidtransWebhookController extends Controller
{
    public function handle(Request $request)
    {
        // 1) Kalau bukan POST (HEAD/GET tes Midtrans), langsung sukses
        if (! $request->isMethod('post')) {
            return response()->json(['message' => 'OK'], 200);
        }


        // 2) Siapkan kredensial dinamis untuk Midtrans SDK
        MidtransConfig::$serverKey    = setting('midtrans_server_key', config('services.midtrans.server_key'));
        MidtransConfig::$clientKey    = setting('midtrans_client_key', config('services.midtrans.client_key'));
        MidtransConfig::$isProduction = setting('midtrans_mode', config('services.midtrans.is_production') ? 'production' : 'sandbox') === 'production';
        MidtransConfig::$isSanitized  = true;
        MidtransConfig::$is3ds        = true;

        // 3) Baca payload mentah dan decode
        $payload = json_decode($request->getContent(), true) ?: [];
        Log::info('Midtrans Raw Payload:', $payload);

        // 4) Verifikasi signature (penting untuk keamanan!)
        if (
            !isset($payload['order_id'], $payload['status_code'], $payload['gross_amount'], $payload['signature_key']) ||
            hash("sha512", $payload['order_id'] . $payload['status_code'] . $payload['gross_amount'] . MidtransConfig::$serverKey)
            !== $payload['signature_key']
        ) {
            Log::warning('Midtrans Notification: Invalid signature or missing payload keys.', ['payload' => $payload]);
            return response()->json(['error' => 'Invalid signature or payload'], 400);
        }

        // 5) PARSE ORDER ID & STATUS
        $orderIdRaw        = $payload['order_id']          ?? null;
        $transactionStatus = $payload['transaction_status']  ?? null;
        $fraudStatus       = $payload['fraud_status']        ?? null; // Tambahkan fraud_status

        if (! $orderIdRaw || ! $transactionStatus) {
            Log::warning('Midtrans Notification: Missing order_id or transaction_status in payload.', ['payload' => $payload]);
            return response()->json(['error' => 'Invalid payload format'], 400);
        }

        // format: ORDER-{id}-{timestamp}
        $parts   = explode('-', $orderIdRaw);
        $orderId = $parts[1] ?? null; // Ambil ID order yang asli
        if (! $orderId) {
            Log::warning('Midtrans Notification: Invalid order_id format.', ['order_id_raw' => $orderIdRaw]);
            return response()->json(['error' => 'Invalid order_id format'], 400);
        }

        // 6) TEMUKAN ORDER & UPDATE STATUS
        $order = Order::find($orderId);
        if (! $order) {
            Log::warning('Midtrans Notification: Order not found in database.', ['order_id' => $orderId]);
            return response()->json(['error' => 'Order not found'], 404);
        }

        $oldStatus = $order->status; // Simpan status lama

        // Logika update status
        if ($transactionStatus == 'capture') {
            if ($fraudStatus == 'challenge') {
                $order->status = 'challenge';
            } else if ($fraudStatus == 'accept') {
                $order->status = 'paid';
            }
        } else if ($transactionStatus == 'settlement') {
            $order->status = 'paid';
        } else if ($transactionStatus == 'pending') {
            $order->status = 'pending';
        } else if ($transactionStatus == 'deny') {
            $order->status = 'denied';
        } else if ($transactionStatus == 'expire') {
            $order->status = 'expired';
        } else if ($transactionStatus == 'cancel') {
            $order->status = 'cancelled';
        } else {
            // Default case untuk status yang tidak dikenal atau tidak perlu diubah
            // Biarkan status order tidak berubah atau set ke default jika perlu
            Log::info('Midtrans Notification: Unhandled transaction status or fraud status', [
                'order_id' => $order->id,
                'transaction_status' => $transactionStatus,
                'fraud_status' => $fraudStatus
            ]);
        }

        // Simpan payload utuh dalam format JSON string
        $order->payment_info = json_encode($payload);
        $order->save();


        // Dispatch Job untuk mengirim notifikasi WhatsApp hanya jika status berubah ke 'paid'
        if ($oldStatus !== 'paid' && $order->status === 'paid') {
            SendWhatsAppNotification::dispatch($order, 'payment_success');
            Log::info('WhatsApp notification dispatched for successful payment. Order ID: ' . $order->id);
        } elseif ($oldStatus === 'pending' && $order->status === 'cancelled') {
            // Contoh: Mengirim notifikasi pembatalan
            // SendWhatsAppNotification::dispatch($order, 'payment_cancelled'); // Jika Anda membuat tipe notifikasi ini
            // Anda bisa menambahkan logika dispatch untuk status lain jika diperlukan,
            // misalnya untuk notifikasi pembatalan, tapi itu opsional.
        }
        // Anda bisa menambahkan logika dispatch untuk status lain jika diperlukan

        // 7) RETURN 200 OK ke Midtrans
        return response()->json(['success' => true]);
    }

    // Metode sendWhatsAppSuccess yang lama telah dihapus dan diganti dengan Job
}
