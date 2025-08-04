@extends('layouts.app')
@section('content')
<h1 class="text-xl font-bold mb-4">Terima Kasih</h1>
@if($order->status === 'paid')
<p>Pesanan Anda berhasil. Silakan unduh produk berikut:</p>
<ul class="list-disc pl-6 mt-2">
    @foreach($order->items as $item)
    <li>{{ $item->product->name }} @if($item->variant)- {{ $item->variant->name }} @endif</li>
    @endforeach
</ul>
<a href="{{ route('magic.link', $order->magic_link_token) }}" class="text-blue-600 underline mt-4 inline-block">Download
    Produk</a>
@else
<p>Menunggu pembayaran...</p>
@endif
@endsection