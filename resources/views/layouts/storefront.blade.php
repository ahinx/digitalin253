<!doctype html>
<html lang="id" class="h-full">

<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover" />
    <meta name="theme-color" content="#0f172a" />
    <title>{{ setting('app_name_public', 'Digitalin - Etalase') }}</title>
    @vite(['resources/css/app.css','resources/js/app.js'])
    @livewireStyles
</head>

<body class="min-h-dvh bg-gray-50 text-gray-900 antialiased">

    @php
    $brand = setting('app_name_public', config('app.name','Digitalinku'));
    $logoVal = setting('app_image');
    if (is_string($logoVal) && $logoVal !== '') {
    $t = ltrim($logoVal,'/');
    $abs = str_starts_with($t,'http://') || str_starts_with($t,'https://') || str_starts_with($t,'data:');
    $logoUrl = $abs ? $t : (str_starts_with($t,'storage/') ? asset($t) : asset('storage/'.$t));
    } else {
    $logoUrl = null;
    }
    $cartCount = collect(session('cart', []))->sum(fn($i) => (int)($i['quantity'] ?? 1));
    @endphp

    {{-- ================= HEADER ================= --}}
    <header class="sticky top-0 z-50 bg-white/90 backdrop-blur border-b border-gray-100">
        <div class="max-w-7xl mx-auto h-14 px-4 flex items-center gap-3">
            {{-- Brand --}}
            <a href="{{ route('shop.index') }}" class="shrink-0 flex items-center gap-2">
                <div class="h-8 w-8 rounded-lg overflow-hidden ring-1 ring-gray-200 grid place-items-center bg-white">
                    @if($logoUrl)
                    <img src="{{ $logoUrl }}" alt="logo" class="h-full w-full object-cover">
                    @else
                    <span class="text-[10px] font-semibold text-gray-500">LOGO</span>
                    @endif
                </div>
                <span class="font-semibold tracking-tight">{{ $brand }}</span>
            </a>

            {{-- Search (desktop only) --}}
            <form action="{{ route('shop.index') }}" method="GET" class="hidden lg:flex items-center flex-1 min-w-0">
                <div class="relative w-full">
                    <input name="q" value="{{ request('q') }}" placeholder="Cari produk digital…" class="w-full rounded-xl border border-gray-200 bg-white py-2 pl-10 pr-3 text-sm
                      focus:outline-none focus:ring-2 focus:ring-blue-500">
                    <svg class="absolute left-3 top-1/2 -translate-y-1/2 h-5 w-5 text-gray-400" viewBox="0 0 24 24"
                        fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="11" cy="11" r="7"></circle>
                        <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
                    </svg>
                </div>
            </form>

            {{-- Desktop nav --}}
            <nav class="hidden md:flex items-center gap-5 text-sm text-gray-600">
                <a href="{{ route('shop.index') }}"
                    class="{{ request()->routeIs('shop.index') ? 'text-blue-600 font-semibold' : 'hover:text-gray-900' }}">Home</a>
                <a href="{{ route('shop.trackOrder') }}"
                    class="{{ request()->routeIs('shop.trackOrder*') ? 'text-blue-600 font-semibold' : 'hover:text-gray-900' }}">Lacak</a>
                <span class="opacity-60">Akun</span>
                <a href="{{ route('shop.cart') }}"
                    class="relative {{ request()->routeIs('shop.cart') ? 'text-blue-600 font-semibold' : 'hover:text-gray-900' }}">
                    Cart
                    @if($cartCount > 0)
                    <span
                        class="absolute -right-3 -top-2 h-5 min-w-[1.1rem] px-1 rounded-full bg-blue-600 text-white text-[10px] grid place-items-center">
                        {{ $cartCount }}
                    </span>
                    @endif
                </a>
            </nav>

            {{-- Actions (mobile) --}}
            <div class="md:hidden flex items-center gap-1 ml-auto">
                <button type="button" id="open-search" class="p-2 rounded-xl hover:bg-gray-100" aria-label="Cari">
                    <svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="11" cy="11" r="7"></circle>
                        <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
                    </svg>
                </button>
                <a href="{{ route('shop.cart') }}" class="relative p-2 rounded-xl hover:bg-gray-100"
                    aria-label="Keranjang">
                    <svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M6 6h15l-1.5 9h-12z" />
                        <path d="M6 6l-2 0" />
                        <circle cx="9" cy="20" r="1" />
                        <circle cx="18" cy="20" r="1" />
                    </svg>
                    @if($cartCount > 0)
                    <span
                        class="absolute -right-1 -top-1 h-5 min-w-[1.1rem] px-1 rounded-full bg-blue-600 text-white text-[10px] grid place-items-center">
                        {{ $cartCount }}
                    </span>
                    @endif
                </a>
            </div>
        </div>
    </header>

    {{-- ================ HERO (SATU KALI SAJA) ================ --}}
    <section class="border-b border-gray-100 bg-gradient-to-br from-blue-600 to-indigo-600">
        <div class="max-w-7xl mx-auto px-4 py-8 md:py-12 text-white">
            <div class="grid md:grid-cols-12 gap-6 items-center">
                <div class="md:col-span-7">
                    <h1 class="text-2xl md:text-4xl font-bold leading-tight">
                        Semua produk digital, sekali klik 🚀
                    </h1>
                    <p class="mt-2 text-white/90">
                        Template, e-book, lisensi, plugin, course—semua dalam satu tempat.
                    </p>
                    <div class="mt-5 flex gap-3">
                        <a href="#produk"
                            class="rounded-xl bg-white text-gray-900 px-4 py-2 text-sm font-semibold">Jelajah Produk</a>
                        <a href="#promo"
                            class="rounded-xl bg-white/10 text-white px-4 py-2 text-sm font-semibold ring-1 ring-inset ring-white/40">Lihat
                            Promo</a>
                    </div>
                </div>
                <div class="md:col-span-5">
                    <div class="h-28 md:h-40 rounded-2xl bg-white/10 ring-1 ring-white/30"></div>
                </div>
            </div>

            {{-- Search (mobile) --}}
            <form action="{{ route('shop.index') }}" method="GET" class="mt-6 md:hidden">
                <div class="relative">
                    <input name="q" value="{{ request('q') }}" placeholder="Cari produk digital…" class="w-full rounded-xl border border-white/20 bg-white/10 text-white placeholder-white/70
                      py-2 pl-10 pr-3 text-sm focus:outline-none focus:ring-2 focus:ring-white/70">
                    <svg class="absolute left-3 top-1/2 -translate-y-1/2 h-5 w-5 text-white/80" viewBox="0 0 24 24"
                        fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="11" cy="11" r="7"></circle>
                        <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
                    </svg>
                </div>
            </form>
        </div>
    </section>

    <main class="min-h-[calc(100dvh-3.5rem)] pb-20 md:pb-0">
        @yield('content')
        {{ $slot ?? '' }}
    </main>

    {{-- Bottom Nav (mobile) --}}
    <nav class="md:hidden fixed bottom-0 inset-x-0 z-50 border-t border-gray-200 bg-white"
        style="padding-bottom: env(safe-area-inset-bottom)">
        <div class="grid grid-cols-4 text-xs">
            <a href="{{ route('shop.index') }}"
                class="py-2.5 flex flex-col items-center {{ request()->routeIs('shop.index') ? 'text-blue-600' : 'text-gray-600' }}">🏠<span>Home</span></a>
            <a href="{{ route('shop.trackOrder') }}"
                class="py-2.5 flex flex-col items-center {{ request()->routeIs('shop.trackOrder*') ? 'text-blue-600' : 'text-gray-600' }}">📦<span>Lacak</span></a>
            <a class="py-2.5 flex flex-col items-center text-gray-400 cursor-default">👤<span>Akun</span></a>
            <a href="{{ route('shop.cart') }}"
                class="py-2.5 flex flex-col items-center {{ request()->routeIs('shop.cart') ? 'text-blue-600' : 'text-gray-600' }}">🛒<span>Cart</span></a>
        </div>
    </nav>

    {{-- Midtrans (global, sekali saja) --}}
    <script src="https://app.sandbox.midtrans.com/snap/snap.js"
        data-client-key="{{ config('services.midtrans.client_key') }}"></script>

    <script>
        // util agar redirect selalu ke thank-you (fallback onClose juga)
  window.startSnapPay = function (token, orderId, thankUrl) {
    if (!token) return;
    var done = false;
    function finish() {
      if (done) return;
      done = true;
      const u = new URL(thankUrl || "{{ route('shop.thankyou') }}", location.origin);
      if (orderId) u.searchParams.set('order_id', orderId);
      location.href = u.toString();
    }
    // guard kalau callback tidak terpanggil
    const guard = setTimeout(finish, 3000);

    window.snap.pay(token, {
      onSuccess: function(){ clearTimeout(guard); finish(); },
      onPending: function(){ clearTimeout(guard); finish(); },
      onError: function(){ clearTimeout(guard); finish(); },
      onClose: function(){ clearTimeout(guard); finish(); }
    });
  };
    </script>
    @livewireScripts
    @stack('scripts')

</body>

</html>