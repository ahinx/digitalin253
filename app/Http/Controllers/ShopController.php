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
use App\Models\Voucher;
use App\Services\MidtransService;

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
        $subtotalPrice = 0.0; // Mengganti totalPrice menjadi subtotalPrice untuk kejelasan

        foreach ($cart as $key => $entry) {
            $product = Product::find($entry['product_id']);
            if (!$product) {
                Log::warning('Produk tidak ditemukan di database saat melihat keranjang: ID ' . $entry['product_id']);
                unset($cart[$key]);
                Session::put('cart', $cart);
                continue;
            }

            $variant = null;
            $price = (float) $product->price;
            $itemName = $product->name;
            $quantity = (int) ($entry['quantity'] ?? 1);

            if (!empty($entry['variant_id'])) {
                $variant = $product->variants()->find($entry['variant_id']);
                if ($variant) {
                    $price = (float) $variant->price;
                    $itemName .= ' - ' . $variant->name;
                } else {
                    Log::warning('Varian produk tidak ditemukan di database saat melihat keranjang: ID ' . $entry['variant_id'] . ' untuk Produk ID ' . $entry['product_id']);
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
                'quantity' => $quantity,
                'subtotal' => $price * $quantity,
            ];
            $subtotalPrice += ($price * $quantity);
        }

        $appliedVoucher = Session::get('applied_voucher');
        $discountAmount = 0;
        $finalPrice = $subtotalPrice;

        if ($appliedVoucher) {
            // Validasi ulang voucher di sini (opsional tapi disarankan)
            $voucher = Voucher::where('code', $appliedVoucher['code'])->first();
            if ($voucher && $voucher->expires_at && $voucher->expires_at->isPast()) {
                Session::forget('applied_voucher'); // Hapus jika kadaluarsa
                $appliedVoucher = null;
            } elseif ($voucher && $voucher->usage_limit && $voucher->used_count >= $voucher->usage_limit) {
                Session::forget('applied_voucher'); // Hapus jika batas penggunaan tercapai
                $appliedVoucher = null;
            }

            if ($appliedVoucher) {
                $discountAmount = (float) $appliedVoucher['discount'];
                $finalPrice = $subtotalPrice - $discountAmount;
                if ($finalPrice < 0) $finalPrice = 0; // Pastikan total tidak negatif
            }
        }

        // Kirim semua data ke view
        return view('shop.cart', compact('items', 'subtotalPrice', 'appliedVoucher', 'discountAmount', 'finalPrice'));
    }


    /**
     * Memperbarui kuantitas item di keranjang atau menghapusnya.
     */
    public function updateCart(Request $request)
    {
        $data = $request->validate([
            'product_id' => 'required|exists:products,id',
            'variant_id' => 'nullable|exists:product_variants,id',
            'action' => 'required|in:inc,dec,remove', // increment, decrement, remove
        ]);

        $cart = Session::get('cart', []);
        $itemKey = null;

        // Cari item di keranjang
        foreach ($cart as $key => $item) {
            if ($item['product_id'] == $data['product_id'] && ($item['variant_id'] ?? null) == ($data['variant_id'] ?? null)) {
                $itemKey = $key;
                break;
            }
        }

        if ($itemKey === null) {
            return response()->json(['success' => false, 'error' => 'Item tidak ditemukan di keranjang.'], 404);
        }

        DB::beginTransaction();
        try {
            if ($data['action'] === 'inc') {
                $cart[$itemKey]['quantity'] = ($cart[$itemKey]['quantity'] ?? 1) + 1;
            } elseif ($data['action'] === 'dec') {
                $cart[$itemKey]['quantity'] = ($cart[$itemKey]['quantity'] ?? 1) - 1;
                if ($cart[$itemKey]['quantity'] <= 0) {
                    unset($cart[$itemKey]); // Hapus jika kuantitas <= 0
                }
            } elseif ($data['action'] === 'remove') {
                unset($cart[$itemKey]);
            }

            // Re-index array setelah unset
            $cart = array_values($cart);
            Session::put('cart', $cart);
            DB::commit();

            // Hitung kembali jumlah item di keranjang untuk badge
            $cartCount = array_sum(array_column($cart, 'quantity'));

            return response()->json(['success' => true, 'count' => $cartCount]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to update cart item: ' . $e->getMessage(), ['request' => $request->all()]);
            return response()->json(['success' => false, 'error' => 'Gagal memperbarui item keranjang. Silakan coba lagi.'], 500);
        }
    }



    /**
     * Menerapkan kode voucher ke keranjang.
     */
    public function applyVoucher(Request $request)
    {
        $request->validate([
            'voucher_code' => 'required|string|max:255',
        ]);

        $voucherCode = $request->voucher_code;
        $subtotalPrice = 0;

        // Hitung subtotal keranjang saat ini (dari session)
        $cart = Session::get('cart', []);
        if (empty($cart)) {
            return response()->json(['success' => false, 'error' => 'Keranjang kosong.']);
        }
        foreach ($cart as $entry) {
            $product = Product::find($entry['product_id']);
            if (!$product) continue;
            $price = (float) $product->price;
            if (!empty($entry['variant_id'])) {
                $variant = $product->variants()->find($entry['variant_id']);
                if ($variant) $price = (float) $variant->price;
            }
            $quantity = (int) ($entry['quantity'] ?? 1);
            $subtotalPrice += ($price * $quantity);
        }

        if ($subtotalPrice <= 0) {
            return response()->json(['success' => false, 'error' => 'Total keranjang harus lebih dari nol untuk menerapkan voucher.']);
        }

        $voucher = Voucher::where('code', $voucherCode)->first();

        if (!$voucher) {
            return response()->json(['success' => false, 'error' => 'Kode voucher tidak valid.']);
        }

        if ($voucher->expires_at && $voucher->expires_at->isPast()) {
            return response()->json(['success' => false, 'error' => 'Voucher sudah kadaluarsa.']);
        }

        if ($voucher->usage_limit && $voucher->used_count >= $voucher->usage_limit) {
            return response()->json(['success' => false, 'error' => 'Voucher sudah mencapai batas penggunaan.']);
        }

        // Hitung diskon
        $calculatedDiscount = 0;
        if ($voucher->discount_type === 'fixed') {
            $calculatedDiscount = (float) $voucher->value;
        } elseif ($voucher->discount_type === 'percent') {
            $calculatedDiscount = ($subtotalPrice * (float) $voucher->value) / 100;
        }

        // Pastikan diskon tidak melebihi subtotal
        if ($calculatedDiscount > $subtotalPrice) {
            $calculatedDiscount = $subtotalPrice;
        }

        // Simpan voucher di session
        Session::put('applied_voucher', [
            'id' => $voucher->id,
            'code' => $voucher->code,
            'discount_type' => $voucher->discount_type,
            'value' => (float) $voucher->value, // Simpan nilai asli voucher
            'discount' => round($calculatedDiscount), // Simpan diskon yang dihitung (bulatkan ke integer)
        ]);

        $finalPrice = $subtotalPrice - round($calculatedDiscount);
        if ($finalPrice < 0) $finalPrice = 0;

        return response()->json([
            'success' => true,
            'message' => 'Voucher berhasil diterapkan!',
            'discount_amount' => round($calculatedDiscount),
            'final_price' => $finalPrice,
        ]);
    }

    /**
     * Menghapus voucher dari keranjang.
     */
    public function removeVoucher()
    {
        Session::forget('applied_voucher');
        return response()->json(['success' => true, 'message' => 'Voucher berhasil dihapus.']);
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

        $subtotal = 0.0;
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
                $subtotal += ($price * $quantity);
            }

            if ($subtotal <= 0) {
                throw new \Exception('Total keranjang harus lebih dari nol.');
            }

            $discountAmount = 0;
            $voucherId = null;
            $appliedVoucher = Session::get('applied_voucher');

            if ($appliedVoucher) {
                $voucher = Voucher::find($appliedVoucher['id']); // Ambil voucher dari DB lagi
                // Validasi ulang voucher sebelum finalisasi order (PENTING!)
                if (
                    $voucher && $voucher->code === $appliedVoucher['code'] &&
                    (!$voucher->expires_at || !$voucher->expires_at->isPast()) &&
                    (!$voucher->usage_limit || $voucher->used_count < $voucher->usage_limit)
                ) {

                    $discountAmount = (float) $appliedVoucher['discount'];
                    $voucherId = $voucher->id;

                    // Pastikan diskon tidak melebihi subtotal
                    if ($discountAmount > $subtotal) {
                        $discountAmount = $subtotal;
                    }
                } else {
                    // Voucher tidak valid lagi, hapus dari session
                    Session::forget('applied_voucher');
                    Log::warning('Applied voucher became invalid during checkout: ' . ($voucher->code ?? 'N/A'));
                }
            }

            $finalTotal = $subtotal - $discountAmount;
            if ($finalTotal < 0) $finalTotal = 0; // Pastikan total tidak negatif

            $trackingKey = Str::random(6);

            $order = Order::create([
                'buyer_name' => $request->name,
                'email' => $request->email,
                'phone' => $request->phone,
                'status' => 'pending',
                'total_price' => $finalTotal, // Gunakan finalTotal setelah diskon
                'discount_amount' => $discountAmount, // Simpan jumlah diskon
                'voucher_id' => $voucherId, // Simpan voucher_id
                'magic_link_token' => Str::uuid(),
                'tracking_key' => $trackingKey,
            ]);

            // Tingkatkan used_count voucher jika digunakan
            if ($voucherId) {
                Voucher::where('id', $voucherId)->increment('used_count');
            }

            foreach ($cart as $entry) {
                $product = Product::find($entry['product_id']);
                $price_item = (float) $product->price;
                $quantity_item = (int) ($entry['quantity'] ?? 1);

                $deliverableType = null;
                $deliverableId = null;

                if (!empty($entry['variant_id'])) {
                    $variant = $product->variants()->find($entry['variant_id']);
                    if ($variant) {
                        $price_item = (float) $variant->price;
                        if ($variant->downloadable_type && ($variant->file_path || $variant->external_url)) {
                            $deliverableType = \App\Models\ProductVariant::class;
                            $deliverableId = $variant->id;
                        }
                    } else {
                        Log::warning('Varian produk tidak ditemukan saat membuat OrderItem: ID ' . $entry['variant_id']);
                        continue;
                    }
                } else {
                    if ($product->type === 'simple' && $product->downloadable_type && ($product->file_path || $product->external_url)) {
                        $deliverableType = \App\Models\Product::class;
                        $deliverableId = $product->id;
                    }
                }

                OrderItem::create([
                    'order_id' => $order->id,
                    'product_id' => $entry['product_id'],
                    'product_variant_id' => $entry['variant_id'] ?? null,
                    'price' => $price_item,
                    'quantity' => $quantity_item,
                    'deliverable_type' => $deliverableType,
                    'deliverable_id' => $deliverableId,
                ]);
            }

            Session::forget('cart');
            Session::forget('applied_voucher'); // Hapus voucher dari session setelah sukses

            MidtransService::configure();

            $transaction = [
                'transaction_details' => [
                    'order_id' => 'ORDER-' . $order->id . '-' . time(),
                    'gross_amount' => $finalTotal, // Gunakan finalTotal untuk Midtrans
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

    /**
     * Menampilkan detail produk berdasarkan slug.
     *
     * @param \App\Models\Product $product
     * @return \Illuminate\Contracts\View\View
     */
    public function show(Product $product)
    {
        // Eager load varian produk jika ada
        $product->loadMissing('variants');

        return view('shop.show', compact('product'));
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


    /**
     * Menampilkan halaman lacak pesanan dan memproses pencarian.
     *
     * @param Request $request
     * @return \Illuminate\Contracts\View\View
     */
    public function trackOrder(Request $request)
    {
        $orders = collect();
        $phone = $request->input('phone');
        $trackingKeyInput = $request->input('tracking_key');
        $searchPerformed = false;
        $snapTokens = [];
        $autoSnapOrderId = $request->query('auto_snap_order_id');
        $displayMessage = null;

        // --- Scenario 1: Coming from a paymentLink (auto_snap_order_id is present in query string) ---
        if ($autoSnapOrderId) {
            $orderToSnap = Order::find($autoSnapOrderId);

            // Validate that the order exists and is pending.
            // We no longer strictly validate tracking_key from URL here, as signed URL is primary security.
            if ($orderToSnap && $orderToSnap->status === 'pending') {
                // Pre-fill form fields with this order's data
                $phone = $orderToSnap->phone;
                $trackingKeyInput = $orderToSnap->tracking_key; // Pre-fill with actual key from DB
                $searchPerformed = true; // Indicate that a search result (single order) is being displayed

                // Only display THIS specific order if coming from auto-snap link
                $orders = collect([$orderToSnap]);

                // Generate Snap Token for this specific order if pending
                // Panggil MidtransService untuk konfigurasi
                MidtransService::configure();

                $grossAmount = (float) $orderToSnap->total_price;

                if ($grossAmount <= 0) {
                    Log::error('Total harga order di trackOrder (auto-snap) nol atau negatif: ' . $orderToSnap->id . ' Harga: ' . $grossAmount);
                    $snapTokens[$orderToSnap->id] = null;
                    $displayMessage = 'Gagal memuat pembayaran untuk pesanan ini (harga tidak valid).';
                } else {
                    $midtransItems = [];
                    $orderToSnap->loadMissing('items.product', 'items.variant');
                    foreach ($orderToSnap->items as $item) {
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
                            'order_id'     => 'ORDER-TRACK-' . $orderToSnap->id . '-' . time(),
                            'gross_amount' => $grossAmount,
                        ],
                        'customer_details' => [
                            'first_name' => $orderToSnap->buyer_name,
                            'email'      => $orderToSnap->email,
                            'phone'      => $orderToSnap->phone,
                        ],
                        'item_details' => $midtransItems,
                    ];

                    try {
                        $snapTokens[$orderToSnap->id] = Snap::getSnapToken($txn);
                    } catch (\Exception $e) {
                        Log::error('Gagal mendapatkan Snap Token di trackOrder (auto-snap): ' . $e->getMessage(), ['order_id' => $orderToSnap->id, 'total_price' => $orderToSnap->total_price]);
                        $snapTokens[$orderToSnap->id] = null;
                        $displayMessage = 'Gagal memuat opsi pembayaran. Silakan coba lagi.';
                    }
                }
            } else {
                // Order not found, not pending, or signed URL invalid (though signedRoute handles this).
                // Clear autoSnapOrderId to prevent JS from triggering Snap.
                $autoSnapOrderId = null;
                $displayMessage = 'Tautan pembayaran otomatis tidak valid atau pesanan tidak ditemukan.';
                // IMPORTANT: Do NOT set $searchPerformed = true; here if we don't want to show results.
                // We let the user perform a manual search if they want.
            }
        }
        // --- Handle manual form submission scenario (POST request) ---
        else if ($request->isMethod('post')) {
            $searchPerformed = true; // A search was attempted via form
            if (empty($phone) || empty($trackingKeyInput)) {
                $displayMessage = 'Nomor WhatsApp dan Kunci Pelacakan harus diisi untuk mencari pesanan.';
            } else {
                $cleanPhone = preg_replace('/[^0-9]/', '', $phone);

                // For manual search, both phone and tracking_key MUST match
                $orders = Order::where('phone', 'like', '%' . $cleanPhone . '%')
                    ->whereRaw('BINARY tracking_key = ?', [$trackingKeyInput])
                    ->with('items.product', 'items.variant')
                    ->orderBy('created_at', 'desc')
                    ->get();

                if ($orders->isEmpty()) {
                    $displayMessage = 'Tidak ada pesanan yang ditemukan dengan kombinasi Nomor WhatsApp dan Kunci Pelacakan tersebut.';
                } else {
                    // If orders are found via manual search, generate snap tokens for pending ones
                    foreach ($orders as $order) {
                        if ($order->status === 'pending') {

                            // Panggil MidtransService untuk konfigurasi
                            MidtransService::configure();
                            $grossAmount = (float) $order->total_price;

                            if ($grossAmount <= 0) {
                                Log::error('Total harga order di trackOrder (manual) nol atau negatif: ' . $order->id . ' Harga: ' . $grossAmount);
                                $snapTokens[$order->id] = null;
                                continue;
                            }

                            $midtransItems = [];
                            $order->loadMissing('items.product', 'items.variant');
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
                                Log::error('Gagal mendapatkan Snap Token di trackOrder (manual): ' . $e->getMessage(), ['order_id' => $order->id, 'total_price' => $order->total_price]);
                                $snapTokens[$order->id] = null;
                            }
                        }
                    }
                }
            }
        }
        // --- Initial GET request to /track-order (no search performed yet) ---
        // In this case, $searchPerformed remains false, $orders remains empty.
        // The view will just show the empty form.

        return view('shop.track-order', compact('orders', 'phone', 'trackingKeyInput', 'searchPerformed', 'snapTokens', 'autoSnapOrderId', 'displayMessage'));
    }
}
