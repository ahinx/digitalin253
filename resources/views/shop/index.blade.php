<!-- resources/views/shop/index.blade.php -->
@extends('layouts.app')

@section('content')
<h1 class="text-2xl font-semibold mb-4">Etalase Produk</h1>

<div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-4">
    @foreach($products as $product)
    <div class="bg-white rounded-lg shadow p-4 flex flex-col">
        <!-- Gambar & nama -->
        <img src="{{ asset('storage/'.$product->main_image) }}" alt="" class="h-32 object-cover mb-2">
        <h2 class="font-medium text-sm flex-1">{{ $product->name }}</h2>
        <p class="text-red-500 font-bold mt-2">Rp{{ number_format($product->price,0,',','.') }}</p>

        <!-- Pilihan Varian (jika ada) -->
        @if($product->variants->count())
        <select id="variant-{{ $product->id }}" class="border p-1 mt-2 text-sm">
            <option value="">— Pilih Varian —</option>
            @foreach($product->variants as $variant)
            <option value="{{ $variant->id }}">
                {{ $variant->name }} — Rp{{ number_format($variant->price,0,',','.') }}
            </option>
            @endforeach
        </select>
        @endif

        <!-- Tombol Keranjang & Beli -->
        <div class="mt-3 grid grid-cols-2 gap-2">
            <button onclick="addToCart({{ $product->id }}, getSelectedVariant({{ $product->id }}))"
                class="bg-blue-500 text-white py-1 rounded text-xs">
                Keranjang
            </button>
            <button onclick="buyNow({{ $product->id }}, getSelectedVariant({{ $product->id }}))"
                class="bg-green-500 text-white py-1 rounded text-xs">
                Beli
            </button>
        </div>
    </div>
    @endforeach
</div>

<script>
    function getSelectedVariant(productId) {
      const sel = document.getElementById('variant-' + productId);
      return (sel && sel.value) ? sel.value : null;
    }

    function addToCart(productId, variantId) {
  if (document.getElementById('variant-' + productId) && !variantId) {
    return alert('Silakan pilih varian terlebih dahulu.');
  }

  fetch('{{ secure_url(route('shop.addToCart', [], false)) }}', {
    method: 'POST',
    headers: {
      'X-CSRF-TOKEN': '{{ csrf_token() }}',
      'Content-Type': 'application/json',
      'Accept': 'application/json'
    },
    body: JSON.stringify({
      product_id: productId,
      variant_id: variantId
    })
  })
  .then(res => res.json())
  .then(data => {
    if (data.success) {
      alert('Berhasil ditambahkan ke keranjang!');
    } else {
      alert('Gagal menambahkan ke keranjang');
    }
  })
  .catch(err => {
    console.error(err);
    alert('Terjadi kesalahan saat menambahkan ke keranjang');
  });
}

function buyNow(productId, variantId) {
  if (document.getElementById('variant-' + productId) && !variantId) {
    return alert('Silakan pilih varian terlebih dahulu.');
  }

  fetch('{{ secure_url(route('shop.addToCart', [], false)) }}', {
    method: 'POST',
    headers: {
      'X-CSRF-TOKEN': '{{ csrf_token() }}',
      'Content-Type': 'application/json',
      'Accept': 'application/json'
    },
    body: JSON.stringify({
      product_id: productId,
      variant_id: variantId
    })
  })
  .then(res => res.json())
  .then(data => {
    if (data.success) {
      window.location.href = '{{ secure_url(route('shop.cart', [], false)) }}';
    } else {
      alert('Gagal memproses pembelian.');
    }
  })
  .catch(err => {
    console.error(err);
    alert('Terjadi kesalahan saat membeli produk.');
  });
}


    
</script>




@endsection