<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Container\Attributes\Log;
use Midtrans\Config as MidtransConfig;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Midtrans\Config;
use Midtrans\Snap;

class ShopController extends Controller
{
    public function index()
    {
        $products = Product::with('variants')->get();
        return view('shop.index', compact('products'));
    }

    public function addToCart(Request $request)
    {
        $data = $request->validate([
            'product_id' => 'required|exists:products,id',
            'variant_id' => 'nullable|exists:product_variants,id',
        ]);

        $cart = Session::get('cart', []);
        $cart[] = [
            'product_id' => $data['product_id'],
            'variant_id' => $data['variant_id'] ?? null,
        ];
        Session::put('cart', $cart);

        return response()->json(['success' => true]);
    }

    public function viewCart()
    {
        $cart = Session::get('cart', []);
        $items = [];

        foreach ($cart as $entry) {
            $product = Product::find($entry['product_id']);
            $variant = $entry['variant_id'] ?? null
                ? $product->variants()->find($entry['variant_id'])
                : null;
            $items[] = ['product' => $product, 'variant' => $variant];
        }

        return view('shop.cart', compact('items'));
    }



    public function checkout(Request $request)
    {


        $request->validate([
            'name' => 'required',
            'email' => 'required|email',
            'phone' => 'required'
        ]);

        $cart = Session::get('cart', []);
        if (!$cart || count($cart) === 0) {
            return response()->json(['error' => 'Keranjang kosong'], 400);
        }

        $total = 0;
        foreach ($cart as $entry) {
            $product = Product::find($entry['product_id']);
            $price = $entry['variant_id'] ?? null
                ? $product->variants()->find($entry['variant_id'])->price
                : $product->price;
            $total += $price;
        }

        $order = Order::create([
            'buyer_name' => $request->name,
            'email' => $request->email,
            'phone' => $request->phone,
            'status' => 'pending',
            'total_price' => $total,
            'magic_link_token' => Str::uuid()
        ]);

        foreach ($cart as $entry) {
            $product = Product::find($entry['product_id']);
            $price = $entry['variant_id'] ?? null
                ? $product->variants()->find($entry['variant_id'])->price
                : $product->price;

            OrderItem::create([
                'order_id' => $order->id,
                'product_id' => $entry['product_id'],
                'product_variant_id' => $entry['variant_id'] ?? null, // ← ini benar
                'price' => $price,
            ]);
        }

        Session::forget('cart');


        // ambil konfigurasi dinamis dari database
        $serverKey = setting('midtrans_server_key', config('services.midtrans.server_key'));
        $clientKey = setting('midtrans_client_key', config('services.midtrans.client_key'));
        $mode      = setting('midtrans_mode', config('services.midtrans.is_production') ? 'production' : 'sandbox');


        // set Midtrans SDK
        MidtransConfig::$serverKey    = $serverKey;
        MidtransConfig::$clientKey    = $clientKey;
        MidtransConfig::$isProduction = $mode === 'production';
        MidtransConfig::$isSanitized  = true;
        MidtransConfig::$is3ds        = true;

        $transaction = [
            'transaction_details' => [
                'order_id' => 'ORDER-' . $order->id . '-' . time(),
                'gross_amount' => $total,
            ],
            'customer_details' => [
                'first_name' => $order->buyer_name,
                'email' => $order->email,
                'phone' => $order->phone,
            ],
        ];

        $snapToken = Snap::getSnapToken($transaction);

        // 🔔 Kirim WhatsApp Link Pembayaran
        $this->sendWhatsAppPaymentLink($order);

        return response()->json([
            'snapToken' => $snapToken,
            'orderId' => $order->id
        ]);
    }

    /**
     * Halaman “payment link” untuk buyer, yang langsung
     * menampilkan Snap UI (bisa dipanggil dari WA link).
     */
    public function paymentLink(Order $order)
    {
        // Set Midtrans SDK sama seperti di checkout()
        $serverKey = setting('midtrans_server_key', config('services.midtrans.server_key'));
        $clientKey = setting('midtrans_client_key', config('services.midtrans.client_key'));
        $mode      = setting('midtrans_mode', config('services.midtrans.is_production') ? 'production' : 'sandbox');

        MidtransConfig::$serverKey    = $serverKey;
        MidtransConfig::$clientKey    = $clientKey;
        MidtransConfig::$isProduction = $mode === 'production';
        MidtransConfig::$isSanitized  = true;
        MidtransConfig::$is3ds        = true;

        // Regenerate snapToken agar bisa dipakai kembali
        $txn = [
            'transaction_details' => [
                'order_id'     => 'ORDER-' . $order->id . '-' . time(),
                'gross_amount' => $order->total_price,
            ],
            'customer_details' => [
                'first_name' => $order->buyer_name,
                'email'      => $order->email,
                'phone'      => $order->phone,
            ],
        ];
        $snapToken = Snap::getSnapToken($txn);

        return view('shop.payment', compact('order', 'snapToken'));
    }

    /**
     * Internal: kirim WhatsApp via Fonnte API (POST).
     */
    private function sendWhatsAppPaymentLink(Order $order): void
    {
        $apiUrl = setting('whatsapp_api_url');     // ex: https://api.fonnte.com/send
        $token  = setting('whatsapp_api_token');   // Fonnte Account Token

        // Link yang akan dibuka: ke method paymentLink()
        $paymentUrl = route('shop.paymentLink', ['order' => $order->id]);

        // Format pesan
        $amount  = number_format($order->total_price, 0, ',', '.');
        $message = "Halo {$order->buyer_name}, terima kasih telah memesan (Order #{$order->id}). Total pembayaran: Rp{$amount}.\nSilakan bayar via: {$paymentUrl}";

        // Kirim ke Fonnte :contentReference[oaicite:0]{index=0}
        Http::withHeaders([
            'Authorization' => $token,
        ])->post($apiUrl, [
            'target'      => $order->phone,
            'message'     => $message,
            'countryCode' => '62',
        ]);
    }



    public function thankYou(Request $request)
    {
        $order = Order::with('items.product', 'items.variant')->findOrFail($request->order_id);
        return view('shop.thank-you', compact('order'));
    }



    public function testWhatsApp(Request $request)
    {
        $phone = $request->query('phone');
        if (! $phone) {
            return response()->json([
                'error' => 'Parameter "phone" (dengan kode negara tanpa +) dibutuhkan, misal 628123456789'
            ], 400);
        }

        // Baca setting Filament
        $apiUrl = setting('whatsapp_api_url');
        $token  = setting('whatsapp_api_token');

        // Pesan uji coba
        $message = "🔔 Ini pesan uji coba WhatsApp dari aplikasi kamu.\n" .
            "Jika kamu terima pesan ini, konfigurasi Fonnte sudah benar.";

        // Kirim ke Fonnte
        try {
            $response = Http::withHeaders([
                'Authorization' => $token,
            ])->post($apiUrl, [
                'target'      => $phone,
                'message'     => $message,
                'countryCode' => substr($phone, 0, 2), // misal "62"
            ]);

            return response()->json([
                'sent_to'   => $phone,
                'status'    => $response->status(),
                'body'      => $response->json(),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error'   => 'Gagal kirim: ' . $e->getMessage(),
            ], 500);
        }
    }
}
