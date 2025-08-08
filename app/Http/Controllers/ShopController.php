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
use Illuminate\Support\Facades\Log; // Import Log facade
use App\Jobs\SendWhatsAppNotification; // Import Job yang baru

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
        // $found = false;

        // Cek jika produk/varian sudah ada di keranjang untuk menghindari duplikasi
        foreach ($cart as $key => $item) {
            if ($item['product_id'] == $data['product_id'] && ($item['variant_id'] ?? null) == ($data['variant_id'] ?? null)) {
                // Jika Anda ingin menambahkan kuantitas, lakukan di sini
                // Untuk saat ini, kita anggap setiap penambahan adalah item baru
                $found = true;
                break;
            }
        }

        // Tambahkan item baru ke keranjang
        $cart[] = [
            'product_id' => $data['product_id'],
            'variant_id' => $data['variant_id'] ?? null,
            // 'quantity' => 1, // Anda bisa menambahkan kuantitas di sini jika diperlukan
        ];

        Session::put('cart', $cart);

        return response()->json(['success' => true]);
    }

    public function viewCart()
    {
        $cart = Session::get('cart', []);
        $items = [];
        $totalPrice = 0; // Inisialisasi total harga untuk ditampilkan di keranjang

        foreach ($cart as $entry) {
            $product = Product::find($entry['product_id']);
            if (!$product) {
                // Log atau handle jika produk tidak ditemukan di database
                Log::warning('Produk tidak ditemukan di database: ID ' . $entry['product_id']);
                continue; // Lanjutkan ke item berikutnya
            }

            $variant = null;
            $price = (float) $product->price; // Harga default adalah harga produk
            $itemName = $product->name;

            if (!empty($entry['variant_id'])) {
                $variant = $product->variants()->find($entry['variant_id']);
                if ($variant) {
                    $price = (float) $variant->price;
                    $itemName .= ' - ' . $variant->name; // Tambahkan nama varian ke nama item
                } else {
                    // Log atau handle jika varian tidak ditemukan
                    Log::warning('Varian produk tidak ditemukan: ID ' . $entry['variant_id'] . ' untuk Produk ID ' . $entry['product_id']);
                }
            }

            $items[] = [
                'product' => $product,
                'variant' => $variant,
                'price' => $price, // Sertakan harga yang benar untuk ditampilkan
                'name' => $itemName, // Nama item yang akan ditampilkan
            ];
            $totalPrice += $price; // Tambahkan ke total harga keranjang
        }

        return view('shop.cart', compact('items', 'totalPrice')); // Kirim totalPrice ke view
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

        $total = 0.0; // Inisialisasi sebagai float
        $midtransItems = []; // Array untuk item details Midtrans

        foreach ($cart as $entry) {
            $product = Product::find($entry['product_id']);
            if (!$product) {
                Log::error('Produk tidak ditemukan saat checkout: ID ' . $entry['product_id']);
                return response()->json(['error' => 'Ada produk di keranjang yang tidak valid.'], 400);
            }

            $price = (float) $product->price;
            $itemName = $product->name;

            if (!empty($entry['variant_id'])) {
                $variant = $product->variants()->find($entry['variant_id']);
                if ($variant) {
                    $price = (float) $variant->price;
                } else {
                    Log::error('Varian produk tidak ditemukan saat checkout: ID ' . $entry['variant_id'] . ' untuk Produk ID ' . $entry['product_id']);
                    return response()->json(['error' => 'Ada varian produk di keranjang yang tidak valid.'], 400);
                }
            }

            // Penting: Pastikan harga positif setelah konversi
            if ($price <= 0) {
                Log::error('Harga produk atau varian nol atau negatif saat checkout: ID ' . $product->id . ' Harga: ' . $price);
                return response()->json(['error' => 'Harga produk tidak valid (nol atau negatif).'], 400);
            }

            // Tambahkan item ke array Midtrans
            $midtransItems[] = [
                'id'       => $product->id . (isset($entry['variant_id']) ? '-' . $entry['variant_id'] : ''), // ID unik
                'price'    => $price,
                'quantity' => 1, // Asumsi kuantitas 1 per item di keranjang
                'name'     => $itemName,
            ];
            $total += $price; // Akumulasi total harga
        }

        // Pengecekan total setelah semua item dihitung
        if ($total <= 0) {
            return response()->json(['error' => 'Total harga pesanan harus lebih dari nol.'], 400);
        }

        // Buat order baru di database
        $order = Order::create([
            'buyer_name' => $request->name,
            'email' => $request->email,
            'phone' => $request->phone,
            'status' => 'pending', // Status awal selalu pending
            'total_price' => $total,
            'magic_link_token' => Str::uuid()
        ]);

        // Simpan item-item order
        foreach ($cart as $entry) {
            $product = Product::find($entry['product_id']);
            $price = (float) $product->price;
            if (!empty($entry['variant_id'])) {
                $variant = $product->variants()->find($entry['variant_id']);
                if ($variant) {
                    $price = $variant->price;
                }
            }
            OrderItem::create([
                'order_id' => $order->id,
                'product_id' => $entry['product_id'],
                'product_variant_id' => $entry['variant_id'] ?? null,
                'price' => (float) $price,
            ]);
        }

        Session::forget('cart'); // Kosongkan keranjang setelah order dibuat

        // Ambil konfigurasi Midtrans
        MidtransConfig::$serverKey    = setting('midtrans_server_key', config('services.midtrans.server_key'));
        MidtransConfig::$clientKey    = setting('midtrans_client_key', config('services.midtrans.client_key'));
        MidtransConfig::$isProduction = setting('midtrans_mode', config('services.midtrans.is_production') ? 'production' : 'sandbox') === 'production';
        MidtransConfig::$isSanitized  = true;
        MidtransConfig::$is3ds        = true;

        // Siapkan parameter transaksi Midtrans
        $transaction = [
            'transaction_details' => [
                'order_id' => 'ORDER-' . $order->id . '-' . time(), // Format order_id harus unik
                'gross_amount' => $total,
            ],
            'customer_details' => [
                'first_name' => $order->buyer_name,
                'email' => $order->email,
                'phone' => $order->phone,
            ],
            'item_details' => $midtransItems, // <-- Kirim detail item
            // 'callbacks' => [ // Opsional: Definisikan callback URL di sini jika berbeda dari dashboard Midtrans
            //     'finish' => route('shop.thankYou', ['order_id' => $order->id]),
            //     'error' => route('shop.thankYou', ['order_id' => $order->id]), // Atau halaman error khusus
            //     'pending' => route('shop.thankYou', ['order_id' => $order->id]), // Atau halaman pending khusus
            // ],
        ];

        try {
            $snapToken = Snap::getSnapToken($transaction);
        } catch (\Exception $e) {
            Log::error('Gagal mendapatkan Snap Token dari Midtrans: ' . $e->getMessage(), ['order_id' => $order->id]);
            return response()->json(['error' => 'Gagal memproses pembayaran. Silakan coba lagi. E: ' . $e->getMessage()], 500);
        }

        // Dispatch Job untuk mengirim notifikasi WhatsApp (tipe 'payment_reminder')
        SendWhatsAppNotification::dispatch($order, 'payment_reminder');

        return response()->json([
            'snapToken' => $snapToken,
            'orderId' => $order->id
        ]);
    }

    /**
     * Halaman “payment link” untuk buyer, yang langsung
     * menampilkan Snap UI (bisa dipanggil dari WA link).
     * Sekarang akan redirect ke halaman lacak pesanan dan memicu Snap.
     */
    public function paymentLink(Order $order)
    {
        // Redirect ke halaman lacak pesanan dengan parameter untuk memicu Snap
        return redirect()->route('shop.trackOrder', [
            'phone' => $order->phone, // Kirim nomor telepon untuk pencarian
            'auto_snap_order_id' => $order->id // Kirim ID order untuk memicu Snap
        ]);
    }

    // Metode sendWhatsAppPaymentLink yang lama telah dihapus dan diganti dengan Job

    public function thankYou(Request $request)
    {
        // Pastikan order_id ada dan valid
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

        // Baca setting Filament
        $apiUrl = setting('whatsapp_api_url');
        $token  = setting('whatsapp_api_token');

        if (empty($apiUrl) || empty($token)) {
            return response()->json(['error' => 'WhatsApp API URL atau Token belum dikonfigurasi.'], 400);
        }

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
                'countryCode' => '62', // Asumsi nomor telepon selalu diawali 62
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
                'details' => $e->getTraceAsString() // Untuk debugging lebih lanjut
            ], 500);
        }
    }

    /**
     * Menampilkan halaman lacak pesanan dan memproses pencarian.
     *
     * @param Request $request
     * @return \Illuminate\Contracts\View\View
     */
    public function trackOrder(Request $request)
    {
        $orders = collect(); // Koleksi kosong secara default
        $phone = $request->input('phone');
        $searchPerformed = false;
        $snapTokens = []; // Untuk menyimpan snap tokens untuk pesanan pending
        $autoSnapOrderId = $request->query('auto_snap_order_id'); // Ambil parameter auto_snap_order_id

        // Jika ada auto_snap_order_id, kita perlu mencari order tersebut terlebih dahulu
        if ($autoSnapOrderId) {
            $orderToSnap = Order::find($autoSnapOrderId);
            if ($orderToSnap) {
                // Gunakan nomor telepon dari order yang ditemukan untuk pencarian
                $phone = $orderToSnap->phone;
            }
        }

        if ($phone) { // Lakukan pencarian jika ada nomor telepon (baik dari form atau auto_snap)
            $searchPerformed = true;
            $cleanPhone = preg_replace('/[^0-9]/', '', $phone);

            $orders = Order::where('phone', 'like', '%' . $cleanPhone . '%')
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
                    if ($order->relationLoaded('items')) {
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
                                'quantity' => 1,
                                'name'     => $itemName,
                            ];
                        }
                    } else {
                        Log::warning('Relasi "items" tidak dimuat untuk order di trackOrder: ' . $order->id);
                        $midtransItems[] = [
                            'id' => 'ORDER-' . $order->id,
                            'price' => $grossAmount,
                            'quantity' => 1,
                            'name' => 'Pesanan #' . $order->id,
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

        // Teruskan auto_snap_order_id ke view agar JavaScript bisa memicu Snap
        return view('shop.track-order', compact('orders', 'phone', 'searchPerformed', 'snapTokens', 'autoSnapOrderId'));
    }
}
