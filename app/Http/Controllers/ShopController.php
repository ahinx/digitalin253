<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Session;
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

        Config::$serverKey = config('services.midtrans.server_key');
        Config::$isProduction = false;
        Config::$isSanitized = true;
        Config::$is3ds = true;

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
        return response()->json(['snapToken' => $snapToken, 'orderId' => $order->id]);
    }

    public function thankYou(Request $request)
    {
        $order = Order::with('items.product', 'items.variant')->findOrFail($request->order_id);
        return view('shop.thank-you', compact('order'));
    }
}
