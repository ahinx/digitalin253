<?php

namespace App\Http\Controllers;

use App\Models\Order;
use Illuminate\Http\Request;
use Midtrans\Snap;
use Midtrans\Config;
use Illuminate\Support\Facades\Log;

class OrderController extends Controller
{
    public function checkout($orderId)
    {
        $order = Order::with('items.product')->findOrFail($orderId);

        // Konfigurasi Midtrans
        Config::$serverKey = config('services.midtrans.server_key');
        Config::$isProduction = false;
        Config::$isSanitized = true;
        Config::$is3ds = true;

        $transactionDetails = [
            'transaction_details' => [
                'order_id' => 'ORDER-' . $order->id . '-' . now()->timestamp,
                'gross_amount' => $order->items->sum(function ($item) {
                    return $item->variant?->price ?? $item->product->price ?? 0;
                }),
            ],
            'customer_details' => [
                'first_name' => $order->buyer_name,
                'email' => $order->email,
                'phone' => $order->phone,
            ],
        ];

        try {
            $snapToken = Snap::getSnapToken($transactionDetails);
            return view('checkout', compact('snapToken', 'order'));
        } catch (\Exception $e) {
            Log::error('Midtrans error: ' . $e->getMessage());
            return redirect()->back()->withErrors('Gagal memproses pembayaran.');
        }
    }
}
