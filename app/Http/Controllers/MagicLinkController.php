<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Order;
use Illuminate\Support\Facades\Log;

class MagicLinkController extends Controller
{
    public function handle($token)
    {
        // PERBAIKAN: Eager load 'items.deliverable'
        $order = Order::with('items.product', 'items.variant', 'items.deliverable')
            ->where('magic_link_token', $token)
            ->where('status', 'paid')
            ->first();

        if (! $order) {
            abort(404, 'Link tidak valid atau pembayaran belum berhasil.');
        }

        // // Logika logging item ini bagus untuk debugging, bisa dipertahankan atau dihapus setelah yakin berfungsi
        // foreach ($order->items as $item) {
        //     Log::info('Item:', [
        //         'product' => $item->product->name,
        //         'variant' => $item->variant ? $item->variant->name : null,
        //         // Mengakses file_path/external_url dari deliverable
        //         'deliverable_type' => $item->deliverable ? $item->deliverable->getMorphClass() : null,
        //         'deliverable_id' => $item->deliverable ? $item->deliverable->id : null,
        //         'file_path_from_deliverable' => optional($item->deliverable)->file_path,
        //         'external_url_from_deliverable' => optional($item->deliverable)->external_url,
        //     ]);
        // }

        return view('shop.magic-link-download', compact('order'));
    }
}
