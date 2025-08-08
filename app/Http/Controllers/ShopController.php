<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Midtrans\Config as MidtransConfig;
use Midtrans\Snap;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB; // Import DB facade untuk transaksi
use App\Jobs\SendWhatsAppNotification;

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
        $itemAdded = false;

        // Memulai transaksi database (opsional untuk addToCart, tapi konsisten)
        DB::beginTransaction();
        try {
            // Cek jika produk/varian sudah ada di keranjang, tambahkan kuantitasnya
            foreach ($cart as $key => $item) {
                if ($item['product_id'] == $data['product_id'] && ($item['variant_id'] ?? null) == ($data['variant_id'] ?? null)) {
                    $cart[$key]['quantity'] = ($cart[$key]['quantity'] ?? 1) + 1; // Tambahkan kuantitas
                    $itemAdded = true;
                    break;
                }
            }

            // Jika item belum ada di keranjang, tambahkan sebagai entri baru
            if (!$itemAdded) {
                $cart[] = [
                    'product_id' => $data['product_id'],
                    'variant_id' => $data['variant_id'] ?? null,
                    'quantity' => 1, // Kuantitas awal
                ];
            }

            Session::put('cart', $cart);
            DB::commit(); // Commit transaksi

            return response()->json(['success' => true]);
        } catch (\Exception $e) {
            DB::rollBack(); // Rollback transaksi jika ada exception
            Log::error('Failed to add to cart: ' . $e->getMessage(), ['request' => $request->all()]);
            return response()->json(['success' => false, 'error' => 'Gagal menambahkan ke keranjang. Silakan coba lagi.'], 500);
        }
    }

    public function viewCart()
    {
        $cart = Session::get('cart', []);
        $items = [];
        $totalPrice = 0.0;

        foreach ($cart as $key => $entry) { // Tambahkan $key untuk bisa menghapus/mengubah item
            $product = Product::find($entry['product_id']);
            if (!$product) {
                Log::warning('Produk tidak ditemukan di database saat melihat keranjang: ID ' . $entry['product_id']);
                // Hapus item yang tidak valid dari keranjang
                unset($cart[$key]);
                Session::put('cart', $cart);
                continue;
            }

            $variant = null;
            $price = (float) $product->price;
            $itemName = $product->name;
            $quantity = (int) ($entry['quantity'] ?? 1); // Ambil kuantitas

            if (!empty($entry['variant_id'])) {
                $variant = $product->variants()->find($entry['variant_id']);
                if ($variant) {
                    $price = (float) $variant->price;
                    $itemName .= ' - ' . $variant->name;
                } else {
                    Log::warning('Varian produk tidak ditemukan di database saat melihat keranjang: ID ' . $entry['variant_id'] . ' untuk Produk ID ' . $entry['product_id']);
                    // Hapus item yang tidak valid dari keranjang
                    unset($cart[$key]);
                    Session::put('cart', $cart);
                    continue;
                }
            }

            $items[] = [
                'product' => $product,
                'variant' => $variant,
                'price' => $price,
                'name' => $itemName,
                'quantity' => $quantity, // Sertakan kuantitas
                'subtotal' => $price * $quantity, // Hitung subtotal
            ];
            $totalPrice += ($price * $quantity); // Tambahkan ke total harga keranjang
        }

        return view('shop.cart', compact('items', 'totalPrice'));
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
            return response()->json(['error' => 'Keranjang kosong. Silakan tambahkan produk terlebih dahulu.'], 400);
        }

        $total = 0.0;
        $midtransItems = [];

        DB::beginTransaction();
        try {
            foreach ($cart as $entry) {
                $product = Product::find($entry['product_id']);
                if (!$product) {
                    Log::error('Produk tidak ditemukan saat checkout: ID ' . $entry['product_id']);
                    throw new \Exception('Ada produk di keranjang yang tidak valid.');
                }

                $price = (float) $product->price;
                $itemName = $product->name;
                $quantity = (int) ($entry['quantity'] ?? 1);

                if (!empty($entry['variant_id'])) {
                    $variant = $product->variants()->find($entry['variant_id']);
                    if ($variant) {
                        $price = (float) $variant->price;
                        $itemName .= ' - ' . $variant->name;
                    } else {
                        Log::error('Varian produk tidak ditemukan saat checkout: ID ' . $entry['variant_id'] . ' untuk Produk ID ' . $entry['product_id']);
                        throw new \Exception('Ada varian produk di keranjang yang tidak valid.');
                    }
                }

                if ($price <= 0) {
                    Log::error('Harga produk atau varian nol atau negatif saat checkout: ID ' . $product->id . ' Harga: ' . $price);
                    throw new \Exception('Harga produk tidak valid (nol atau negatif).');
                }

                $midtransItems[] = [
                    'id'       => $product->id . (isset($entry['variant_id']) ? '-' . $entry['variant_id'] : ''),
                    'price'    => $price,
                    'quantity' => $quantity,
                    'name'     => $itemName,
                ];
                $total += ($price * $quantity);
            }

            if ($total <= 0) {
                throw new \Exception('Total harga pesanan harus lebih dari nol.');
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
                $price_item = (float) $product->price;
                $quantity_item = (int) ($entry['quantity'] ?? 1);

                if (!empty($entry['variant_id'])) {
                    $variant = $product->variants()->find($entry['variant_id']);
                    if ($variant) {
                        $price_item = (float) $variant->price;
                    }
                }
                OrderItem::create([
                    'order_id' => $order->id,
                    'product_id' => $entry['product_id'],
                    'product_variant_id' => $entry['variant_id'] ?? null,
                    'price' => $price_item, // <<< PERBAIKAN: Sertakan kolom 'price'
                    'quantity' => $quantity_item, // <<< PERBAIKAN: Sertakan kolom 'quantity'
                    // Kolom timestamps (created_at, updated_at) akan otomatis diisi oleh Eloquent
                ]);
            }

            Session::forget('cart');

            MidtransConfig::$serverKey    = setting('midtrans_server_key', config('services.midtrans.server_key'));
            MidtransConfig::$clientKey    = setting('midtrans_client_key', config('services.midtrans.client_key'));
            MidtransConfig::$isProduction = setting('midtrans_mode', config('services.midtrans.is_production') ? 'production' : 'sandbox') === 'production';
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
                'item_details' => $midtransItems,
            ];

            $snapToken = Snap::getSnapToken($transaction);

            DB::commit();

            SendWhatsAppNotification::dispatch($order, 'payment_reminder');

            return response()->json([
                'snapToken' => $snapToken,
                'orderId' => $order->id
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Checkout failed: ' . $e->getMessage(), ['cart' => $cart, 'request' => $request->all()]);
            return response()->json(['error' => 'Gagal memproses pesanan: ' . $e->getMessage()], 500);
        }
    }

    public function paymentLink(Order $order)
    {
        return redirect()->route('shop.trackOrder', [
            'phone' => $order->phone,
            'auto_snap_order_id' => $order->id
        ]);
    }

    public function thankYou(Request $request)
    {
        $request->validate([
            'order_id' => 'required|exists:orders,id',
        ]);
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

        $apiUrl = setting('whatsapp_api_url');
        $token  = setting('whatsapp_api_token');

        if (empty($apiUrl) || empty($token)) {
            return response()->json(['error' => 'WhatsApp API URL atau Token belum dikonfigurasi.'], 400);
        }

        $message = "🔔 Ini pesan uji coba WhatsApp dari aplikasi kamu.\n" .
            "Jika kamu terima pesan ini, konfigurasi Fonnte sudah benar.";

        try {
            $response = Http::withHeaders([
                'Authorization' => $token,
            ])->post($apiUrl, [
                'target'      => $phone,
                'message'     => $message,
                'countryCode' => '62',
            ]);

            return response()->json([
                'sent_to'   => $phone,
                'status'    => $response->status(),
                'body'      => $response->json(),
                'note'      => 'Pastikan nomor telepon dimulai dengan 62 tanpa + (misal: 62812xxxx).'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error'   => 'Gagal kirim: ' . $e->getMessage(),
                'details' => $e->getTraceAsString()
            ], 500);
        }
    }

    public function trackOrder(Request $request)
    {
        $orders = collect();
        $phone = $request->input('phone');
        $searchPerformed = false;
        $snapTokens = [];
        $autoSnapOrderId = $request->query('auto_snap_order_id');

        if ($autoSnapOrderId) {
            $orderToSnap = Order::find($autoSnapOrderId);
            if ($orderToSnap) {
                $phone = $orderToSnap->phone;
            }
        }

        if ($phone) {
            $searchPerformed = true;
            $cleanPhone = preg_replace('/[^0-9]/', '', $phone);

            $orders = Order::where('phone', 'like', '%' . $cleanPhone . '%')
                ->with('items.product', 'items.variant')
                ->orderBy('created_at', 'desc')
                ->get();

            foreach ($orders as $order) {
                if ($order->status === 'pending') {
                    MidtransConfig::$serverKey    = setting('midtrans_server_key', config('services.midtrans.server_key'));
                    MidtransConfig::$clientKey    = setting('midtrans_client_key', config('services.midtrans.client_key'));
                    MidtransConfig::$isProduction = setting('midtrans_mode', config('services.midtrans.is_production') ? 'production' : 'sandbox') === 'production';
                    MidtransConfig::$isSanitized  = true;
                    MidtransConfig::$is3ds        = true;

                    $grossAmount = (float) $order->total_price;

                    if ($grossAmount <= 0) {
                        Log::error('Total harga order di trackOrder nol atau negatif: ' . $order->id . ' Harga: ' . $grossAmount);
                        continue;
                    }

                    $midtransItems = [];
                    foreach ($order->items as $item) {
                        $product = $item->product;
                        $variant = $item->variant;
                        $itemName = $product->name ?? 'Produk Tidak Dikenal';
                        $price = (float) $item->price;

                        if ($variant) {
                            $itemName .= ' - ' . ($variant->name ?? 'Varian Tidak Dikenal');
                        }

                        $midtransItems[] = [
                            'id'       => ($product->id ?? 'unknown') . ($variant->id ?? ''),
                            'price'    => $price,
                            'quantity' => (int)($item->quantity ?? 1),
                            'name'     => $itemName,
                        ];
                    }

                    $txn = [
                        'transaction_details' => [
                            'order_id'     => 'ORDER-TRACK-' . $order->id . '-' . time(),
                            'gross_amount' => $grossAmount,
                        ],
                        'customer_details' => [
                            'first_name' => $order->buyer_name,
                            'email'      => $order->email,
                            'phone'      => $order->phone,
                        ],
                        'item_details' => $midtransItems,
                    ];

                    try {
                        $snapTokens[$order->id] = Snap::getSnapToken($txn);
                    } catch (\Exception $e) {
                        Log::error('Gagal mendapatkan Snap Token di trackOrder: ' . $e->getMessage(), ['order_id' => $order->id, 'total_price' => $order->total_price]);
                        $snapTokens[$order->id] = null;
                    }
                }
            }
        }

        return view('shop.track-order', compact('orders', 'phone', 'searchPerformed', 'snapTokens', 'autoSnapOrderId'));
    }
}
