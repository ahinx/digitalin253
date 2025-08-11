<!-- =============================================================
File: resources/views/shop/cart.blade.php
Memakai variabel dari ShopController@viewCart.
============================================================== -->
@extends('layouts.storefront')

@section('content')
<div class="max-w-4xl mx-auto px-4 md:px-6 py-4 md:py-8">
    <h1 class="text-lg md:text-2xl font-semibold mb-4">Keranjang</h1>

    @if(!empty($items))
    <div class="space-y-3">
        @foreach($items as $it)
        @php
        // Tentukan gambar: varian > produk
        $img = isset($it['variant']) && $it['variant']
        ? variant_image_url($it['variant'], $it['product']->main_image ?? null)
        : product_image_url($it['product'] ?? null);

        // Nama aman
        $name = $it['name'] ?? ($it['product']->name ?? 'Produk');
        if (is_array($name)) $name = implode(', ', $name);

        $price = (float)($it['price'] ?? 0);
        $qty = (int)($it['quantity'] ?? 1);
        $subtotal = (float)($it['subtotal'] ?? ($price * $qty));

        $productId = $it['product']->id ?? null;
        $variantId = $it['variant']->id ?? null;
        @endphp

        <div class="rounded-2xl border border-gray-100 bg-white p-3 md:p-4 flex gap-3">
            <div class="h-16 w-16 rounded-lg bg-gray-100 overflow-hidden shrink-0">
                <img src="{{ $img }}" class="h-full w-full object-cover" alt="{{ $name }}">
            </div>

            <div class="flex-1 min-w-0">
                <div class="font-medium line-clamp-2">{{ $name }}</div>
                <div class="text-sm text-gray-500">
                    Rp{{ number_format($price,0,',','.') }} × {{ $qty }}
                    @if($variantId)
                    <span
                        class="ml-2 inline-block rounded-full bg-gray-100 px-2 py-0.5 text-[11px] text-gray-600">Varian</span>
                    @endif
                </div>
                <div class="mt-1 font-semibold">Subtotal: Rp{{ number_format($subtotal,0,',','.') }}</div>

                <div class="mt-2 flex flex-wrap gap-2">
                    <button class="px-3 h-8 rounded-lg border text-sm" data-action="dec" data-product="{{ $productId }}"
                        data-variant="{{ $variantId ?? '' }}">−</button>

                    <button class="px-3 h-8 rounded-lg border text-sm" data-action="inc" data-product="{{ $productId }}"
                        data-variant="{{ $variantId ?? '' }}">+</button>

                    <button class="px-3 h-8 rounded-lg border text-sm" data-action="remove"
                        data-product="{{ $productId }}" data-variant="{{ $variantId ?? '' }}">Hapus</button>
                </div>
            </div>
        </div>
        @endforeach
    </div>

    {{-- Voucher --}}
    <div class="mt-4 rounded-2xl border border-gray-100 bg-white p-3 md:p-4">
        <form id="voucher-form" class="flex gap-2">
            <input name="voucher_code" placeholder="Kode voucher" value="{{ $appliedVoucher['code'] ?? '' }}"
                class="flex-1 rounded-xl border border-gray-200 py-2 px-3 text-sm">
            <button type="submit" class="rounded-xl bg-gray-900 text-white px-4">Terapkan</button>
            @if($appliedVoucher)
            <button type="button" id="remove-voucher" class="rounded-xl border px-4">Hapus</button>
            @endif
        </form>

        @if($appliedVoucher)
        <div class="mt-2 text-sm text-green-700">
            Voucher <span class="font-semibold">{{ $appliedVoucher['code'] }}</span> diterapkan:
            −Rp{{ number_format($discountAmount,0,',','.') }}
        </div>
        @endif
    </div>

    {{-- Total --}}
    <div class="mt-4 rounded-2xl border border-gray-100 bg-white p-3 md:p-4 space-y-1">
        <div class="flex justify-between">
            <span>Subtotal</span>
            <span>Rp{{ number_format((float)$subtotalPrice,0,',','.') }}</span>
        </div>
        <div class="flex justify-between">
            <span>Diskon</span>
            <span>−Rp{{ number_format((float)$discountAmount,0,',','.') }}</span>
        </div>
        <div class="mt-2 text-lg font-bold flex justify-between">
            <span>Total</span>
            <span>Rp{{ number_format((float)$finalPrice,0,',','.') }}</span>
        </div>
    </div>

    {{-- Checkout form --}}
    <form id="checkout-form" class="mt-4 grid md:grid-cols-3 gap-3">
        <input name="name" class="rounded-xl border border-gray-200 py-2 px-3" placeholder="Nama" required>
        <input name="email" type="email" class="rounded-xl border border-gray-200 py-2 px-3" placeholder="Email"
            required>
        <input name="phone" class="rounded-xl border border-gray-200 py-2 px-3" placeholder="No WhatsApp (628…)"
            required>
        <button class="md:col-span-3 h-11 rounded-xl bg-blue-600 text-white font-semibold">Bayar (Midtrans)</button>
    </form>
    @else
    <div class="text-sm text-gray-500">Keranjang kosong.</div>
    @endif
</div>
@endsection

@push('scripts')
<script>
    // --- Update cart (+ / − / Hapus)
  document.querySelectorAll('[data-action]')?.forEach(btn => {
    btn.addEventListener('click', async () => {
      const body = {
        product_id: btn.dataset.product,
        variant_id: btn.dataset.variant || null,
        action: btn.dataset.action,
      };

      try {
        const res  = await fetch('{{ route('shop.cart.update') }}', {
          method: 'POST',
          headers: {
            'Content-Type':'application/json',
            'Accept':'application/json',
            'X-CSRF-TOKEN':'{{ csrf_token() }}'
          },
          body: JSON.stringify(body)
        });
        const json = await res.json();
        if (json.success) {
          location.reload();
        } else {
          alert(json.error || 'Gagal memperbarui keranjang');
        }
      } catch (e) {
        alert('Terjadi kesalahan jaringan.');
      }
    });
  });

  // --- Voucher apply
  document.getElementById('voucher-form')?.addEventListener('submit', async (e) => {
    e.preventDefault();
    const code = e.target.voucher_code.value.trim();
    if (!code) return;

    try {
      const res = await fetch('{{ route('shop.applyVoucher') }}', {
        method: 'POST',
        headers: {
          'Content-Type':'application/json',
          'Accept':'application/json',
          'X-CSRF-TOKEN':'{{ csrf_token() }}'
        },
        body: JSON.stringify({ voucher_code: code })
      });
      const json = await res.json();
      if (json.success) {
        location.reload();
      } else {
        alert(json.error || 'Gagal menerapkan voucher');
      }
    } catch (e) {
      alert('Terjadi kesalahan jaringan.');
    }
  });

  // --- Voucher remove
  document.getElementById('remove-voucher')?.addEventListener('click', async () => {
    try {
      const res  = await fetch('{{ route('shop.removeVoucher') }}', {
        method: 'POST',
        headers: { 'Accept':'application/json', 'X-CSRF-TOKEN':'{{ csrf_token() }}' }
      });
      const json = await res.json();
      if (json.success) {
        location.reload();
      }
    } catch (e) {
      alert('Terjadi kesalahan jaringan.');
    }
  });

  // --- Checkout (minta Snap token lalu bayar)
  document.getElementById('checkout-form')?.addEventListener('submit', async (e) => {
    e.preventDefault();
    const fd = new FormData(e.target);

    try {
      const res  = await fetch('{{ route('shop.checkout') }}', {
        method: 'POST',
        headers: { 'X-CSRF-TOKEN':'{{ csrf_token() }}' },
        body: fd
      });
      const json = await res.json();

      if (json.snapToken) {
        if (typeof window.snap === 'undefined') {
          alert('Snap belum dimuat.');
          return;
        }
        window.snap.pay(json.snapToken, {
          onSuccess:  () => window.location.href = "{{ route('shop.thankyou') }}?order_id=" + json.orderId,
          onPending:  () => window.location.href = "{{ route('shop.thankyou') }}?order_id=" + json.orderId,
          onError:    () => alert('Pembayaran gagal, coba lagi.'),
          onClose:    () => {} // optional
        });
      } else {
        alert(json.error || 'Gagal checkout');
      }
    } catch (e) {
      alert('Terjadi kesalahan jaringan.');
    }
  });
</script>
@endpush