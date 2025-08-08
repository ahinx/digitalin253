<!-- resources/views/layouts/app.blade.php -->
<!DOCTYPE html>
<html lang="id">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  {{-- Judul dinamis: 'app_name_public' dengan fallback --}}
  <title>{{ setting('app_name_public', 'Digitalin - Etalase') }}</title>

  <script src="https://cdn.tailwindcss.com"></script>
  {{-- Anda bisa menambahkan link CSS kustom di sini jika ada --}}
</head>

<body class="bg-gray-100 flex flex-col min-h-screen">
  <!-- Header for Desktop -->
  <header class="hidden md:flex bg-white shadow p-4">
    <div class="container mx-auto flex justify-between items-center">
      <a href="{{ route('shop.index') }}" class="text-2xl font-bold">{{ setting('app_name_public', 'Digitalin') }}</a>
      <nav class="space-x-6">
        <a href="{{ route('shop.index') }}"
          class="hover:text-blue-500 @if(request()->routeIs('shop.index')) text-blue-600 font-semibold @endif">Home</a>
        <a href="{{ route('shop.cart') }}"
          class="hover:text-blue-500 @if(request()->routeIs('shop.cart')) text-blue-600 font-semibold @endif">Keranjang</a>
        {{-- Menggunakan array rute untuk Lacak Pesanan karena ada rute GET dan POST --}}
        <a href="{{ route('shop.trackOrder') }}"
          class="hover:text-blue-500 @if(request()->routeIs(['shop.trackOrder', 'shop.trackOrder.post'])) text-blue-600 font-semibold @endif">Lacak
          Pesanan</a>
      </nav>
    </div>
  </header>

  <!-- Main Content -->
  <main class="flex-grow container mx-auto px-4 pt-4 pb-16 md:pb-4">
    @yield('content')
  </main>

  <!-- Bottom Navigation Mobile -->
  <nav class="fixed bottom-0 inset-x-0 bg-white shadow-inner p-2 flex justify-around md:hidden z-50">
    <a href="{{ route('shop.index') }}"
      class="flex flex-col items-center text-gray-600 hover:text-blue-500 @if(request()->routeIs('shop.index')) text-blue-600 @endif">
      <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M13 5v6h6" />
      </svg>
      <span class="text-xs">Home</span>
    </a>
    <a href="{{ route('shop.cart') }}"
      class="flex flex-col items-center text-gray-600 hover:text-blue-500 @if(request()->routeIs('shop.cart')) text-blue-600 @endif">
      <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
          d="M3 3h2l.4 2M7 13h14l-1.35 6H6.65L5 13zm0 0L5 6h16M7 13L5 6" />
      </svg>
      <span class="text-xs">Keranjang</span>
    </a>
    {{-- Menggunakan array rute untuk Lacak Pesanan --}}
    <a href="{{ route('shop.trackOrder') }}"
      class="flex flex-col items-center text-gray-600 hover:text-blue-500 @if(request()->routeIs(['shop.trackOrder', 'shop.trackOrder.post'])) text-blue-600 @endif">
      <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2a4 4 0 018 0v2" />
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 11a7 7 0 0114 0v6H5v-6z" />
      </svg>
      <span class="text-xs">Pesanan</span>
    </a>
    <a href="/profile" class="flex flex-col items-center text-gray-600 hover:text-blue-500">
      <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
          d="M5.121 17.804A7.5 7.5 0 0112 15a7.5 7.5 0 016.879 2.804M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
      </svg>
      <span class="text-xs">Akun</span>
    </a>
  </nav>
</body>

</html>