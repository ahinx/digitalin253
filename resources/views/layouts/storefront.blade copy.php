<!-- =============================================================
File: resources/views/layouts/storefront.blade.php
Layout responsif (header + bottom nav). Tidak mengubah controller.
============================================================== -->
<!doctype html>
<html lang="id" class="h-full">

<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover" />
    <meta name="theme-color" content="#0f172a" />
    @php
    // Ambil kandidat judul dari $title atau appSettings
    $rawTitle = $title ?? (function_exists('appSettings') ? appSettings('app_name_public') : null);

    // Jika array/objek, ubah jadi string yang masuk akal
    if (is_array($rawTitle)) {
    $rawTitle = $rawTitle['value'] ?? $rawTitle['name'] ?? (count($rawTitle) ? reset($rawTitle) : null);
    } elseif (is_object($rawTitle)) {
    $rawTitle = method_exists($rawTitle, '__toString') ? (string) $rawTitle : json_encode($rawTitle);
    }

    // Fallback akhir
    $pageTitle = (is_string($rawTitle) && $rawTitle !== '') ? $rawTitle : config('app.name', 'Toko');
    @endphp

    <title>{{ $pageTitle }}</title>
    @vite(['resources/css/app.css','resources/js/app.js'])
    {{-- @include('components.vite-prod') --}}
    @livewireStyles
</head>

<body class="min-h-dvh bg-gray-50 text-gray-900 antialiased">
    <!-- Header -->
    <header class="sticky top-0 z-50 bg-white/90 backdrop-blur border-b border-gray-100">
        <div class="max-w-7xl mx-auto h-14 px-4 flex items-center justify-between">
            @php
            // ambil logo aman (kalau appSettings mengembalikan array)
            $logo = function_exists('appSettings') ? appSettings('app_logo') : null;
            if (is_array($logo)) {
            $logo = $logo['path'] ?? $logo['url'] ?? $logo['value'] ?? null;
            }
            @endphp

            <a href="{{ route('shop.index') }}" class="flex items-center gap-2">
                @if($logo)
                <img src="{{ asset('storage/'.$logo) }}" class="h-7 w-7 rounded-lg object-cover" alt="logo">
                @else
                <div class="h-7 w-7 rounded-lg bg-gray-200"></div>
                @endif
                <span class="font-semibold tracking-tight">
                    @php
                    $rawTitle = $title ?? (function_exists('appSettings') ? appSettings('app_name_public') : null);
                    if (is_array($rawTitle)) { $rawTitle = $rawTitle['value'] ?? $rawTitle['name'] ?? reset($rawTitle);
                    }
                    $brand = (is_string($rawTitle) && $rawTitle !== '') ? $rawTitle : config('app.name','Toko');
                    @endphp
                    {{ $brand }}
                </span>
            </a>

            {{-- search desktop --}}
            <form action="{{ route('shop.index') }}" method="GET"
                class="hidden md:flex items-center gap-2 w-full max-w-md">
                <div class="flex-1 relative">
                    <input type="search" name="q" value="{{ request('q') }}" placeholder="Cari produk digital..."
                        class="w-full rounded-xl border border-gray-200 bg-white py-2 pl-10 pr-3 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                    <svg class="absolute left-3 top-1/2 -translate-y-1/2 h-5 w-5 text-gray-400" viewBox="0 0 24 24"
                        fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="11" cy="11" r="7" />
                        <line x1="21" y1="21" x2="16.65" y2="16.65" />
                    </svg>
                </div>
            </form>

            <div class="flex items-center gap-1 md:gap-2">
                <button type="button" class="md:hidden p-2 rounded-xl hover:bg-gray-100" id="open-search"
                    aria-label="Cari">
                    <svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="11" cy="11" r="7" />
                        <line x1="21" y1="21" x2="16.65" y2="16.65" />
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
                    <span id="cart-badge"
                        class="hidden absolute -top-1.5 -right-1.5 h-5 min-w-[1.25rem] px-1 rounded-full text-[10px] font-semibold bg-red-500 text-white items-center justify-center">0</span>
                </a>
            </div>
        </div>
    </header>


    <!-- Desktop hero -->
    <div class="hidden md:block border-b border-gray-100 bg-gradient-to-br from-blue-600 to-indigo-600">
        <div class="max-w-7xl mx-auto px-4 py-10 text-white">
            <div class="grid grid-cols-12 gap-6">
                <div class="col-span-7">
                    <h1 class="text-3xl font-bold leading-tight">Semua produk digital, sekali klik 🚀</h1>
                    <p class="mt-2 text-white/90">Template, e-book, lisensi, plugin, course—semua dalam satu tempat.</p>
                    <div class="mt-5 flex gap-3">
                        <a href="#produk"
                            class="rounded-xl bg-white text-gray-900 px-4 py-2 text-sm font-semibold">Jelajah Produk</a>
                        <a href="#promo"
                            class="rounded-xl bg-white/10 text-white px-4 py-2 text-sm font-semibold ring-1 ring-inset ring-white/40">Lihat
                            Promo</a>
                    </div>
                </div>
                <div class="col-span-5">
                    <div class="h-40 rounded-2xl bg-white/10 ring-1 ring-white/30"></div>
                </div>
            </div>
        </div>
    </div>

    <main class="min-h-[calc(100dvh-3.5rem)] pb-20 md:pb-0">
        @yield('content')
        {{ $slot ?? '' }}
    </main>

    <!-- Bottom Nav (mobile only) -->
    <nav class="md:hidden fixed bottom-0 inset-x-0 z-50 border-t border-gray-200 bg-white"
        style="padding-bottom: env(safe-area-inset-bottom)">
        <div class="grid grid-cols-4 text-xs">
            <a href="{{ route('shop.index') }}"
                class="py-2.5 flex flex-col items-center {{ request()->routeIs('shop.index') ? 'text-blue-600' : 'text-gray-600' }}">🏠<span>Home</span></a>
            <a href="{{ route('shop.trackOrder') }}"
                class="py-2.5 flex flex-col items-center {{ request()->routeIs('shop.trackOrder*') ? 'text-blue-600' : 'text-gray-600' }}">📦<span>Lacak</span></a>
            <a href="#" class="py-2.5 flex flex-col items-center text-gray-600">👤<span>Akun</span></a>
            <a href="{{ route('shop.cart') }}"
                class="py-2.5 flex flex-col items-center {{ request()->routeIs('shop.cart') ? 'text-blue-600' : 'text-gray-600' }}">🛒<span>Cart</span></a>
        </div>
    </nav>

    @php
    // Ambil Midtrans client key dengan aman (bisa array dari settings)
    $midClient = function_exists('appSettings') ? appSettings('midtrans_client_key') : null;

    if (is_array($midClient)) {
    // ambil kunci umum yang mungkin ada
    $midClient = $midClient['value'] ?? $midClient['key'] ?? $midClient['client_key'] ?? reset($midClient);
    } elseif (is_object($midClient)) {
    $midClient = method_exists($midClient, '__toString') ? (string) $midClient : null;
    }

    if (!is_string($midClient) || $midClient === '') {
    $midClient = env('MIDTRANS_CLIENT_KEY'); // fallback .env
    }
    @endphp

    <!-- Midtrans Snap (sandbox). Untuk production ganti domain & key. -->
    <script src="https://app.sandbox.midtrans.com/snap/snap.js" data-client-key="{{ $midClient }}"></script>


    @livewireScripts
    {{-- @vite('resources/js/app.js') --}}
    @stack('scripts')
</body>

</html>