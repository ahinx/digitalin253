@extends('layouts.app')

@section('content')

{{-- <pre>
    {{ json_encode($order->items->pluck('variant'), JSON_PRETTY_PRINT) }}
</pre> --}}

<div class="max-w-3xl mx-auto p-4">
    <h1 class="text-2xl font-bold mb-4">Download Produk</h1>
    <p>Halo <strong>{{ $order->buyer_name }}</strong>,</p>
    <p class="mb-4">Terima kasih! Berikut adalah produk yang bisa Anda unduh:</p>

    <ul class="space-y-4">
        @foreach ($order->items as $item)
        @php
        $isVariant = $item->variant !== null;
        $source = $isVariant ? $item->variant : $item->product;
        $downloadType = $source->downloadable_type ?? null;
        $filePath = $source->file_path ?? null;
        $externalUrl = $source->external_url ?? null;
        @endphp

        <li class="border p-4 rounded bg-white shadow">
            <p class="font-semibold">
                {{ $item->product->name }} @if($isVariant) - {{ $item->variant->name }} @endif
            </p>

            @if($downloadType === 'file' && $filePath)
            <a href="{{ Storage::url($filePath) }}" class="text-blue-600 underline mt-2 inline-block" download>
                Unduh File
            </a>
            @elseif($downloadType === 'link' && $externalUrl)
            <a href="{{ $externalUrl }}" class="text-blue-600 underline mt-2 inline-block" target="_blank">
                Buka Link
            </a>
            @else
            <p class="text-red-500 mt-2">Tidak ada file atau tautan tersedia.</p>
            @endif
        </li>
        @endforeach
    </ul>
</div>
@endsection