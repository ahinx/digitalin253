<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Order;
use Illuminate\Support\Facades\Log;

class MagicLinkController extends Controller
{

    public function handle($token)
    {
        $order = Order::with('items.product', 'items.variant')
            ->where('magic_link_token', $token)
            ->where('status', 'paid')
            ->first();

        if (! $order) {
            abort(404, 'Link tidak valid atau pembayaran belum berhasil.');
        }

        foreach ($order->items as $item) {
            Log::info('Item:', [
                'product' => $item->product->name,
                'variant' => $item->variant ? $item->variant->name : null,
                'file_path' => optional($item->variant)->file_path,
                'external_url' => optional($item->variant)->external_url,
            ]);
        }

        return view('shop.magic-link-download', compact('order'));
    }
}
