<!-- =============================================================
File: resources/views/shop/index.blade.php
Memakai data $products dari ShopController@index, link detail pakai {product} id.
============================================================== -->
@extends('layouts.storefront')

@section('content')
<div class="max-w-7xl mx-auto px-4 md:px-6 pt-4 md:pt-8">
  <!-- Search mobile -->
  <form action="{{ route('shop.index') }}" method="GET" class="md:hidden mb-3">
    <input name="q" value="{{ request('q') }}" placeholder="Cari produk…"
      class="w-full rounded-xl border border-gray-200 py-2 px-3 text-sm focus:ring-2 focus:ring-blue-500">
  </form>

  @if(isset($products) && $products->count())
  <div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-6 gap-3 md:gap-4">
    @foreach($products as $p)
    @php
    // Nama & kategori aman
    $name = is_array($p->name) ? implode(', ', $p->name) : ($p->name ?? 'Produk');
    $cat = data_get($p, 'category.name', 'Digital');
    if (is_array($cat)) $cat = implode(', ', $cat);

    // Ambil gambar utama dari DB
    $thumb = $p->main_image ?? null;

    // Normalisasi URL gambar:
    // - http/https/data: pakai langsung
    // - path relatif: arahkan ke /storage/...
    if (is_string($thumb) && $thumb !== '') {
    $t = ltrim($thumb, '/');
    $isAbsolute = str_starts_with($t, 'http://') || str_starts_with($t, 'https://') || str_starts_with($t, 'data:');

    if (! $isAbsolute) {
    if (str_starts_with($t, 'storage/')) {
    $thumb = asset($t);
    } else {
    $thumb = asset('storage/'.$t);
    }
    }
    }

    // Fallback placeholder (SVG, tidak 404)
    if (!is_string($thumb) || $thumb === '') {
    $thumb = 'data:image/svg+xml;utf8,' . rawurlencode('
    <svg xmlns="http://www.w3.org/2000/svg" width="600" height="800">
      <rect width="100%" height="100%" fill="#f1f5f9" />
      <text x="50%" y="50%" dominant-baseline="middle" text-anchor="middle" font-family="sans-serif" font-size="20"
        fill="#94a3b8">No image</text>
    </svg>
    ');
    }
    @endphp

    <div class="group rounded-2xl border border-gray-100 bg-white p-2 md:p-3 hover:shadow-sm transition">
      <a href="{{ route('shop.show', $p) }}">
        <div class="aspect-square rounded-xl bg-gray-100 overflow-hidden">
          <img src="{{ product_image_url($p) }}" alt="{{ $p->name }}" class="object-cover">
        </div>
        <div class="mt-2">
          <div class="line-clamp-2 text-sm md:text-[15px] font-medium text-gray-900">{{ (string) $name }}</div>
          <div class="mt-1 text-[13px] text-gray-500">{{ (string) $cat }}</div>
          <div class="mt-1 font-semibold">Rp{{ number_format((float) $p->price,0,',','.') }}</div>
        </div>
      </a>
      <div class="mt-2 flex gap-2">
        <button class="h-9 flex-1 rounded-lg bg-blue-600 text-white text-sm font-semibold" data-add-cart
          data-product-id="{{ $p->id }}" data-variant-id="">
          Tambah
        </button>
        <a href="{{ route('shop.show', $p) }}"
          class="h-9 w-9 rounded-lg border border-gray-200 grid place-items-center">ℹ️</a>
      </div>
    </div>
    @endforeach

  </div>
  @else
  <div class="text-sm text-gray-500">Belum ada produk.</div>
  @endif
</div>
@endsection

@push('scripts')
<script>
  document.querySelectorAll('[data-add-cart]').forEach(btn=>{
    btn.addEventListener('click', async ()=>{
      const product_id = btn.dataset.productId;
      const variant_id = btn.dataset.variantId || '';
      const res = await fetch(`{{ route('shop.addToCart') }}`, {
        method:'POST',
        headers:{ 'Content-Type':'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
        body: JSON.stringify({ product_id, variant_id })
      });
      const json = await res.json();
      if(json.success){ btn.textContent = 'Ditambahkan ✓'; setTimeout(()=>btn.textContent='Tambah', 1200); }
      else { alert(json.error || 'Gagal menambahkan ke keranjang'); }
    });
  });
</script>
@endpush