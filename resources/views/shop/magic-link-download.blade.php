{{-- =============================================================
File: resources/views/shop/magic-link-download.blade.php
Tema storefront; ringkasan order + daftar unduhan.
Tidak mengubah controller/route.
============================================================== --}}
@extends('layouts.storefront')

@section('content')
@php
// Meta ringkas (tidak bikin query baru)
$totalItems = $order->items?->sum('quantity') ?? 0;
$totalPrice = $order->total_price ?? 0;

// Map status → warna badge
$statusStyles = [
'paid' => 'bg-green-100 text-green-700',
'pending' => 'bg-amber-100 text-amber-700',
'expired' => 'bg-amber-100 text-amber-700',
'cancelled' => 'bg-red-100 text-red-600',
'denied' => 'bg-red-100 text-red-600',
];
$badgeClass = $statusStyles[$order->status] ?? 'bg-gray-100 text-gray-700';
@endphp

<div class="max-w-5xl mx-auto px-4 md:px-6 py-6 md:py-8">

    {{-- Ringkasan Order --}}
    <div class="rounded-2xl border border-gray-100 bg-white p-5 md:p-6 shadow-sm">
        <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-3">
            <div>
                <h1 class="text-xl md:text-2xl font-semibold">Unduh Produk</h1>
                <p class="mt-1 text-gray-600">
                    Halo <span class="font-semibold text-gray-900">{{ $order->buyer_name }}</span>,
                    terima kasih atas pembeliannya. Berikut aset digital Anda.
                </p>
            </div>
            <span
                class="inline-flex w-max items-center rounded-full px-2.5 py-1 text-xs font-semibold {{ $badgeClass }}">
                {{ ucfirst($order->status) }}
            </span>
        </div>

        <div class="mt-4 grid grid-cols-1 md:grid-cols-4 gap-3 text-sm">
            <div class="rounded-xl border border-gray-100 bg-gray-50 px-3 py-2">
                <div class="text-gray-500">Order #</div>
                <div class="font-medium text-gray-900">{{ $order->id }}</div>
            </div>
            <div class="rounded-xl border border-gray-100 bg-gray-50 px-3 py-2">
                <div class="text-gray-500">Tanggal</div>
                <div class="font-medium text-gray-900">{{ $order->created_at?->format('d M Y H:i') }}</div>
            </div>
            <div class="rounded-xl border border-gray-100 bg-gray-50 px-3 py-2">
                <div class="text-gray-500">Jumlah Item</div>
                <div class="font-medium text-gray-900">{{ $totalItems }}</div>
            </div>
            <div class="rounded-xl border border-gray-100 bg-gray-50 px-3 py-2">
                <div class="text-gray-500">Total Pembayaran</div>
                <div class="font-bold text-gray-900">Rp{{ number_format($totalPrice, 0, ',', '.') }}</div>
            </div>
        </div>

        {{-- Tracking key kecil (memudahkan user) --}}
        @if(!empty($order->tracking_key))
        <div class="mt-3 text-xs text-gray-500">
            Kunci Pelacakan: <span class="font-semibold text-gray-700" id="trk">{{ $order->tracking_key }}</span>
            <button type="button" id="copy-trk"
                class="ml-2 inline-flex items-center px-2 py-0.5 rounded-lg border text-[11px] text-gray-700 hover:bg-gray-50">
                Salin
            </button>
        </div>
        @endif
    </div>

    {{-- Info jika belum paid --}}
    @if($order->status !== 'paid')
    <div class="mt-4 rounded-2xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-800">
        Pembayaran Anda belum selesai. Setelah pembayaran terkonfirmasi, tombol unduh akan aktif.
    </div>
    @endif

    {{-- Daftar Item Dapat Diunduh --}}
    <div class="mt-6 space-y-3">
        @forelse($order->items as $item)
        @php
        $product = $item->product;
        $variant = $item->variant;
        $src = $item->deliverable; // Product atau ProductVariant
        $downloadType = $src->downloadable_type ?? null; // 'file' | 'link' | null
        $filePath = $src->file_path ?? null;
        $externalUrl = $src->external_url ?? null;

        // thumbnail: varian > produk
        $thumb = $item->product_variant_id
        ? variant_image_url($variant, $product->main_image ?? null)
        : product_image_url($product ?? null);

        $canDownload = $order->status === 'paid' && (
        ($downloadType === 'file' && $filePath) ||
        ($downloadType === 'link' && $externalUrl)
        );
        @endphp

        <div class="rounded-2xl border border-gray-100 bg-white p-4 md:p-5 shadow-sm">
            <div class="grid grid-cols-[64px,1fr] md:grid-cols-[80px,1fr,auto] items-center gap-3 md:gap-4">
                <div class="h-16 w-16 md:h-20 md:w-20 rounded-xl overflow-hidden bg-gray-100">
                    <img src="{{ $thumb }}" alt="{{ $product->name ?? 'Produk' }}" class="h-full w-full object-cover">
                </div>

                <div class="min-w-0">
                    <div class="font-medium text-gray-900 truncate">
                        {{ $product->name ?? 'Produk' }}
                        @if($variant)
                        — <span class="text-gray-600">{{ $variant->name }}</span>
                        @endif
                    </div>
                    <div class="mt-1 text-xs text-gray-500">
                        Qty: {{ (int)($item->quantity ?? 1) }}
                        · Harga: Rp{{ number_format((float)($item->price ?? 0), 0, ',', '.') }}
                        @if($downloadType === 'file')
                        · Tipe: File
                        @elseif($downloadType === 'link')
                        · Tipe: Link
                        @endif
                    </div>
                </div>

                {{-- Tombol aksi (desktop). Di mobile, auto wrap ke bawah --}}
                <div class="justify-self-start md:justify-self-end mt-2 md:mt-0">
                    @if($canDownload)
                    @if($downloadType === 'file')
                    <a href="{{ Storage::url($filePath) }}"
                        class="inline-flex items-center rounded-xl bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 text-sm font-semibold transition"
                        download>
                        Unduh File
                    </a>
                    @else
                    <a href="{{ $externalUrl }}" target="_blank" rel="noopener"
                        class="inline-flex items-center rounded-xl bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 text-sm font-semibold transition">
                        Buka Link
                    </a>
                    @endif
                    @else
                    <span class="inline-flex items-center rounded-xl bg-gray-100 text-gray-600 px-3 py-2 text-sm">
                        {{ $order->status === 'paid' ? 'Tidak tersedia' : 'Menunggu pembayaran' }}
                    </span>
                    @endif
                </div>
            </div>

            @if(!empty($src->short_note))
            <div class="mt-2 text-sm text-gray-600">
                {{ $src->short_note }}
            </div>
            @endif
        </div>
        @empty
        <div class="rounded-2xl border border-gray-100 bg-white p-5 text-sm text-gray-600">
            Tidak ada produk yang dapat diunduh untuk pesanan ini.
        </div>
        @endforelse
    </div>

    {{-- Kembali --}}
    <div class="mt-8 text-center">
        <a href="{{ route('shop.index') }}"
            class="inline-flex items-center rounded-xl border border-gray-200 px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50">
            ← Kembali ke Beranda
        </a>
    </div>
</div>

@push('scripts')
<script>
    document.getElementById('copy-trk')?.addEventListener('click', async ()=>{
    const t = document.getElementById('trk')?.textContent?.trim() || '';
    if(!t) return;
    try { await navigator.clipboard.writeText(t); alert('Kunci pelacakan disalin.'); }
    catch(e){ alert('Gagal menyalin.'); }
  });
</script>
@endpush
@endsection