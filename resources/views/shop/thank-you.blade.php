<!-- =============================================================
File: resources/views/shop/thank-you.blade.php
Memakai $order dari ShopController@thankYou.
============================================================== -->
@extends('layouts.storefront')

@section('content')
<div class="max-w-3xl mx-auto px-4 md:px-6 py-8">
    <h1 class="text-xl md:text-2xl font-semibold">Terima kasih 🎉</h1>
    <p class="mt-2 text-gray-600">Pesanan #{{ $order->id }} sedang diproses.</p>

    <div class="mt-4 rounded-2xl border border-gray-100 bg-white p-4">
        <div class="flex justify-between"><span>Total</span><strong>Rp{{ number_format($order->total_price,0,',','.')
                }}</strong></div>
    </div>

    <div class="mt-6 flex gap-2">
        <a href="{{ route('magic.link', $order->magic_link_token) }}"
            class="rounded-xl bg-blue-600 text-white px-4 py-2">Buka Magic Link Download</a>
        <a href="{{ route('shop.trackOrder', ['phone'=>$order->phone, 'tracking_key'=>$order->tracking_key]) }}"
            class="rounded-xl border px-4 py-2">Lacak Pesanan</a>
    </div>
</div>
@endsection