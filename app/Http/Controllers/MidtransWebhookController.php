<?php

namespace App\Http\Controllers;

use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Midtrans\Config as MidtransConfig;
use Illuminate\Support\Facades\Log;

class MidtransWebhookController extends Controller
{
    public function handle(Request $request)
    {
        // 1) Kalau bukan POST (HEAD/GET tes Midtrans), langsung sukses
        if (! $request->isMethod('post')) {
            return response()->json(['message' => 'OK'], 200);
        }

        // 2) Kalau bukan GET, berarti POST notifikasi Midtrans nyata.
        //    Siapkan kredensial dinamis:
        MidtransConfig::$serverKey    = setting('midtrans_server_key',   config('services.midtrans.server_key'));
        MidtransConfig::$clientKey    = setting('midtrans_client_key',   config('services.midtrans.client_key'));
        MidtransConfig::$isProduction = setting('midtrans_mode',         config('services.midtrans.is_production') ? 'production' : 'sandbox') === 'production';
        MidtransConfig::$isSanitized  = true;
        MidtransConfig::$is3ds        = true;


        // 3) Baca payload mentah dan decode
        $payload = json_decode($request->getContent(), true) ?: [];
        Log::info('Midtrans Raw Payload:', $payload);

        // 4) Verifikasi signature (opsional)
        if (
            isset($payload['signature_key'], $payload['order_id'], $payload['status_code'], $payload['gross_amount'])
            && hash("sha512", $payload['order_id'] . $payload['status_code'] . $payload['gross_amount'] . MidtransConfig::$serverKey)
            !== $payload['signature_key']
        ) {
            Log::warning('Invalid Midtrans signature');
            return response()->json(['error' => 'Invalid signature'], 400);
        }

        //
        // 4) PARSE ORDER ID & STATUS
        //
        $orderIdRaw        = $payload['order_id']            ?? null;
        $transactionStatus = $payload['transaction_status']  ?? null;

        if (! $orderIdRaw || ! $transactionStatus) {
            return response()->json(['error' => 'Invalid payload'], 400);
        }

        // format: ORDER-{id}-{timestamp}
        $parts   = explode('-', $orderIdRaw);
        $orderId = $parts[1] ?? null;
        if (! $orderId) {
            return response()->json(['error' => 'Invalid order_id format'], 400);
        }

        //
        // 5) UPDATE ORDER
        //
        $order = Order::find($orderId);
        if (! $order) {
            return response()->json(['error' => 'Order not found'], 404);
        }

        switch ($transactionStatus) {
            case 'capture':
            case 'settlement':
                $order->status = 'paid';
                break;
            case 'cancel':
            case 'deny':
                $order->status = 'cancelled';
                break;
            case 'expire':
                $order->status = 'expired';
                break;
            default:
                $order->status = 'pending';
                break;
        }

        $order->payment_info = $payload; // simpan utuh
        $order->save();

        $this->sendWhatsAppSuccess($order);

        //
        // 6) RETURN 200 OK
        //
        return response()->json(['success' => true]);
    }

    private function sendWhatsAppSuccess(Order $order): void
    {
        $apiUrl = setting('whatsapp_api_url');
        $token  = setting('whatsapp_api_token');
        $downloadUrl = route('download.magic', ['token' => $order->magic_link_token]);

        $message = "Halo {$order->buyer_name}, pembayaran Order #{$order->id} telah berhasil. Silakan unduh produk Anda di:\n{$downloadUrl}";

        Http::withHeaders([
            'Authorization' => $token,
        ])->post($apiUrl, [
            'target'      => $order->phone,
            'message'     => $message,
            'countryCode' => '62',
        ]);
    }
}
