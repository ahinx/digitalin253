<?php

namespace App\Http\Controllers;

use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class MidtransWebhookController extends Controller
{

    // public function handle(Request $request)
    // {
    //     // Untuk testing, bisa tambahkan log
    //     Log::info('Webhook received:', $request->all());

    //     return response()->json(['message' => 'OK']);
    // }

    public function handle(Request $request)
    {
        $payload = $request->all();
        Log::info('Midtrans Notifikasi Diterima', $payload);

        $orderIdRaw = $payload['order_id'] ?? null;
        if (! $orderIdRaw) {
            return response()->json(['error' => 'No order_id'], 400);
        }

        // Ambil order_id dari format: ORDER-{id}-{timestamp}
        $orderId = explode('-', $orderIdRaw)[1] ?? null;

        $transactionStatus = $payload['transaction_status'] ?? null;

        if (! $orderId || ! $transactionStatus) {
            return response()->json(['error' => 'Invalid data'], 400);
        }

        $order = Order::find($orderId);
        if (! $order) {
            return response()->json(['error' => 'Order not found'], 404);
        }

        // Simpan status pembayaran
        if ($transactionStatus === 'settlement' || $transactionStatus === 'capture') {
            $order->status = 'paid';
        } elseif ($transactionStatus === 'expire') {
            $order->status = 'expired';
        } elseif ($transactionStatus === 'cancel') {
            $order->status = 'cancelled';
        }


        // Simpan payload dari Midtrans ke kolom JSON
        $order->payment_info = $payload;

        $order->save();

        return response()->json(['success' => true]);
    }
}
