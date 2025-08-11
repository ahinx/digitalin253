<!-- =============================================================
File: resources/views/shop/show.blade.php
Memakai $product dari ShopController@show (implicit binding by id).
============================================================== -->
@extends('layouts.storefront')

@section('content')
@if(isset($product))
<div class="max-w-5xl mx-auto px-4 md:px-6 py-4 md:py-8">
    <div class="grid md:grid-cols-2 gap-6">
        {{-- Gambar utama / mengikuti varian --}}
        <div>
            <div class="aspect-square rounded-2xl bg-white border border-gray-100 overflow-hidden">
                <img id="preview-img" src="{{ product_image_url($product) }}" alt="{{ $product->name }}"
                    class="h-full w-full object-cover">
            </div>
        </div>

        {{-- Detail --}}
        <div>
            <h1 class="text-xl md:text-2xl font-semibold">{{ $product->name }}</h1>

            {{-- Harga dinamis --}}
            <div class="mt-2 text-lg font-bold">
                <span id="price-text">Rp {{ number_format($product->price,0,',','.') }}</span>
            </div>

            {{-- Varian (opsional) --}}
            @if($product->variants && $product->variants->count())
            <div class="mt-4">
                <label class="text-sm text-gray-600">Pilih Varian</label>
                <select id="variant" class="mt-1 w-full rounded-xl border border-gray-200 py-2 px-3">
                    <option value="" data-price="{{ $product->price }}" data-img="{{ product_image_url($product) }}">
                        Default
                    </option>
                    @foreach($product->variants as $v)
                    <option value="{{ $v->id }}" data-price="{{ $v->price }}"
                        data-img="{{ variant_image_url($v, $product->main_image) }}">
                        {{ $v->name }} — Rp{{ number_format($v->price,0,',','.') }}
                    </option>
                    @endforeach
                </select>
            </div>
            @endif

            <div class="mt-4 flex gap-3">
                <button id="btn-add" class="h-11 flex-1 rounded-xl bg-blue-600 text-white font-semibold">
                    Tambah ke Keranjang
                </button>
                <a href="{{ route('shop.cart') }}"
                    class="h-11 px-4 rounded-xl border border-gray-200 grid place-items-center">
                    Lihat Cart
                </a>
            </div>

            <div class="mt-6 prose max-w-none">
                {!! $product->description ?? '' !!}
            </div>
        </div>
    </div>
</div>
@else
<div class="max-w-5xl mx-auto px-4 md:px-6 py-8">
    <div class="text-sm text-gray-500">Produk tidak ditemukan.</div>
</div>
@endif
@endsection

@push('scripts')
@if(isset($product))
<script>
    // helper format rupiah sederhana (tanpa library)
  function formatRupiah(n){
    n = Number(n || 0);
    return 'Rp' + n.toLocaleString('id-ID', {maximumFractionDigits:0});
  }

  const selVariant = document.getElementById('variant');
  const priceText  = document.getElementById('price-text');
  const previewImg = document.getElementById('preview-img');

  // Saat ganti varian → update harga & gambar
  selVariant?.addEventListener('change', () => {
    const opt = selVariant.options[selVariant.selectedIndex];
    const price = opt?.dataset?.price;
    const img   = opt?.dataset?.img;

    if (priceText && price) priceText.textContent = formatRupiah(price);
    if (previewImg && img)  previewImg.src = img;
  });

  // Tambah ke keranjang
  document.getElementById('btn-add')?.addEventListener('click', async () => {
    const variant_id = selVariant ? (selVariant.value || '') : '';
    const res = await fetch(`{{ route('shop.addToCart') }}`, {
      method: 'POST',
      headers: {
        'Content-Type':'application/json',
        'X-CSRF-TOKEN': '{{ csrf_token() }}',
        'Accept': 'application/json'
      },
      body: JSON.stringify({
        product_id: '{{ $product->id }}',
        variant_id
      })
    });

    const json = await res.json();
    if (json.success) {
      alert('Ditambahkan ke keranjang');
    } else {
      alert(json.error || 'Gagal menambahkan');
    }
  });
</script>
@endif
@endpush