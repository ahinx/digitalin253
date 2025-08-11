<!-- =============================================================
File: resources/views/shop/track-order.blade.php
Memakai variabel dari ShopController@trackOrder.
============================================================== -->
@extends('layouts.storefront')

@section('content')
<div class="max-w-5xl mx-auto px-4 md:px-6 py-6">
    <h1 class="text-lg md:text-2xl font-semibold mb-4">Lacak Pesanan</h1>

    {{-- Form cari --}}
    <form action="{{ route('shop.trackOrder.post') }}" method="POST" class="grid md:grid-cols-3 gap-3">
        @csrf
        <input name="phone" value="{{ old('phone', $phone) }}" placeholder="No WhatsApp (628…)"
            class="rounded-xl border border-gray-200 py-2 px-3" required>
        <input name="tracking_key" value="{{ old('tracking_key', $trackingKeyInput) }}" placeholder="Kunci Pelacakan"
            class="rounded-xl border border-gray-200 py-2 px-3" required>
        <button class="h-11 rounded-xl bg-blue-600 text-white font-semibold">Cari</button>
    </form>

    @if($displayMessage)
    <div class="mt-4 text-sm text-gray-600">{{ $displayMessage }}</div>
    @endif

    {{-- Hasil --}}
    @if($searchPerformed && $orders->count())
    <div class="mt-6 space-y-4">
        @foreach($orders as $order)
        @php
        $isPending = $order->status === 'pending';
        $snapToken = $snapTokens[$order->id] ?? null;
        @endphp

        <div class="rounded-2xl border border-gray-100 bg-white p-4">
            {{-- Header order --}}
            <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-2">
                <div>
                    <div class="font-medium">Order #{{ $order->id }}</div>
                    <div class="text-sm text-gray-500">
                        Status:
                        <span class="inline-flex items-center rounded-full px-2 py-0.5 text-[11px] font-semibold
                @class([
                  'bg-gray-100 text-gray-700' => $order->status === 'pending',
                  'bg-green-100 text-green-700' => $order->status === 'paid',
                  'bg-amber-100 text-amber-700' => $order->status === 'expired',
                  'bg-red-100 text-red-600' => in_array($order->status, ['cancelled','denied']),
                  'bg-gray-100 text-gray-700' => !in_array($order->status, ['pending','paid','expired','cancelled','denied']),
                ])">
                            {{ ucfirst($order->status) }}
                        </span>
                    </div>
                    <div class="text-xs text-gray-400">Kunci: {{ $order->tracking_key }}</div>
                    <div class="text-xs text-gray-400">Tanggal: {{ $order->created_at?->format('d M Y H:i') }}</div>
                </div>
                <div class="font-semibold text-right">Rp{{ number_format((float)$order->total_price, 0, ',', '.') }}
                </div>
            </div>

            {{-- List item --}}
            <div class="mt-3 divide-y divide-gray-100">
                @foreach($order->items as $it)
                @php
                $img = $it->product_variant_id
                ? variant_image_url($it->variant, $it->product->main_image ?? null)
                : product_image_url($it->product ?? null);
                @endphp
                <div class="py-2 flex gap-3">
                    <div class="h-14 w-14 rounded-lg overflow-hidden bg-gray-100 shrink-0">
                        <img src="{{ $img }}" alt="{{ $it->product->name ?? 'Produk' }}"
                            class="h-full w-full object-cover">
                    </div>
                    <div class="flex-1 min-w-0">
                        <div class="text-sm font-medium truncate">
                            {{ $it->product->name ?? 'Produk' }}
                            @if($it->variant)
                            — <span class="text-gray-600">{{ $it->variant->name }}</span>
                            @endif
                        </div>
                        <div class="text-xs text-gray-500">
                            Rp{{ number_format((float)$it->price,0,',','.') }} × {{ (int)$it->quantity }}
                        </div>
                    </div>
                </div>
                @endforeach
            </div>

            {{-- Aksi sesuai status --}}
            <div class="mt-4">
                @if($isPending && $snapToken)
                <button class="rounded-xl bg-green-600 text-white px-4 py-2" data-pay="{{ $snapToken }}"
                    data-order-id="{{ $order->id }}">
                    Bayar Sekarang
                </button>
                @elseif($order->status === 'paid')
                @if($order->magic_link_token)
                <a href="{{ url('/magic-link/'.$order->magic_link_token) }}"
                    class="rounded-xl bg-gray-900 text-white px-4 py-2 inline-block">Unduh Produk (Magic Link)</a>
                @else
                <div class="text-sm text-gray-600">Link unduhan belum tersedia. Hubungi admin.</div>
                @endif
                @else
                <a href="{{ route('shop.thankyou', ['order_id' => $order->id]) }}"
                    class="rounded-xl border px-4 py-2 inline-block">Lihat Detail</a>
                @endif
            </div>
        </div>
        @endforeach
    </div>
    @endif
</div>
@endsection

@push('scripts')

<script>
    // Bayar via Snap ketika klik tombol
  document.querySelectorAll('[data-pay]')?.forEach(btn => {
    btn.addEventListener('click', () => {
      const token = btn.getAttribute('data-pay');
      const orderId = btn.getAttribute('data-order-id');
      if (typeof window.snap === 'undefined') { alert('Snap belum dimuat.'); return; }
      window.snap.pay(token, {
        onSuccess:  () => window.location.reload(),
        onPending:  () => window.location.reload(),
        onError:    () => alert('Pembayaran gagal, coba lagi.'),
        onClose:    () => {}
      });
    });
  });

  // Auto open Snap jika datang dari paymentLink (?auto_snap_order_id=)
  (function() {
    const autoSnapOrderId = @json($autoSnapOrderId ?? null);
    const snapTokens = @json($snapTokens ?? []);
    if (!autoSnapOrderId) return;

    const token = snapTokens[autoSnapOrderId] || null;
    if (!token) return;

    // Pastikan Snap siap & popup terlihat
    window.scrollTo(0, 0);
    const open = () => {
      if (typeof window.snap === 'undefined') { setTimeout(open, 300); return; }
      window.snap.pay(token, {
        onSuccess:  () => window.location.reload(),
        onPending:  () => window.location.reload(),
        onError:    () => alert('Pembayaran gagal, coba lagi.'),
        onClose:    () => {}
      });
    };
    setTimeout(open, 400);
  })();
</script>
@endpush