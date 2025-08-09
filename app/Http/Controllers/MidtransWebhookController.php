<?php

namespace App\Http\Controllers;

use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Midtrans\Config as MidtransConfig;
use Illuminate\Support\Facades\Log;
use App\Jobs\SendWhatsAppNotification;
use App\Services\MidtransService;

class MidtransWebhookController extends Controller
{
    public function handle(Request $request)
    {
        if (! $request->isMethod('post')) {
            return response()->json(['message' => 'OK'], 200);
        }

        // Panggil MidtransService untuk konfigurasi
        MidtransService::configure();

        $payload = json_decode($request->getContent(), true) ?: [];
        Log::info('Midtrans Raw Payload:', $payload);

        if (
            !isset($payload['order_id'], $payload['status_code'], $payload['gross_amount'], $payload['signature_key']) ||
            hash("sha512", $payload['order_id'] . $payload['status_code'] . $payload['gross_amount'] . MidtransConfig::$serverKey)
            !== $payload['signature_key']
        ) {
            Log::warning('Midtrans Notification: Invalid signature or missing payload keys.', ['payload' => $payload]);
            return response()->json(['error' => 'Invalid signature or payload'], 400);
        }

        $orderIdRaw        = $payload['order_id']          ?? null;
        $transactionStatus = $payload['transaction_status']  ?? null;
        $fraudStatus       = $payload['fraud_status']        ?? null;

        if (! $orderIdRaw || ! $transactionStatus) {
            Log::warning('Midtrans Notification: Missing order_id or transaction_status in payload.', ['payload' => $payload]);
            return response()->json(['error' => 'Invalid payload format'], 400);
        }

        // PERBAIKAN KRUSIAL DI SINI: Sesuaikan parsing orderId
        // Format bisa "ORDER-ID-TIMESTAMP" atau "ORDER-RETRY-ID-TIMESTAMP"
        $parts = explode('-', $orderIdRaw);

        // Cek apakah formatnya "ORDER-RETRY-ID-TIMESTAMP" atau "ORDER-TRACK-ID-TIMESTAMP"
        if (count($parts) >= 4 && ($parts[1] === 'RETRY' || $parts[1] === 'TRACK')) { // Tambah 'TRACK' di sini
            $orderId = $parts[2] ?? null; // Ambil ID order dari posisi ketiga
        } else {
            // Formatnya "ORDER-ID-TIMESTAMP"
            $orderId = $parts[1] ?? null; // Ambil ID order dari posisi kedua
        }


        if (! $orderId || !is_numeric($orderId)) { // Tambahkan is_numeric untuk validasi
            Log::warning('Midtrans Notification: Invalid order_id format or not numeric.', ['order_id_raw' => $orderIdRaw, 'parsed_order_id' => $orderId]);
            return response()->json(['error' => 'Invalid order_id format or not numeric'], 400);
        }

        $order = Order::find($orderId);
        if (! $order) {
            Log::warning('Midtrans Notification: Order not found in database.', ['order_id' => $orderId]);
            return response()->json(['error' => 'Order not found'], 404);
        }

        $oldStatus = $order->status;

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
            Log::info('Midtrans Notification: Unhandled transaction status or fraud status', [
                'order_id' => $order->id,
                'transaction_status' => $transactionStatus,
                'fraud_status' => $fraudStatus
            ]);
        }

        $order->payment_info = json_encode($payload);
        $order->save();

        if ($oldStatus !== 'paid' && $order->status === 'paid') {
            SendWhatsAppNotification::dispatch($order, 'payment_success');
            Log::info('WhatsApp notification dispatched for successful payment. Order ID: ' . $order->id);
        } elseif ($oldStatus === 'pending' && $order->status === 'cancelled') {
            // Contoh: Mengirim notifikasi pembatalan (jika Anda memiliki tipe notifikasi ini di Job)
            // SendWhatsAppNotification::dispatch($order, 'payment_cancelled');
        }

        return response()->json(['success' => true]);
    }
}
