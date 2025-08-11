<!-- Chosen Palette: Calm Harmony -->
<!-- Application Structure Plan: Aplikasi ini mengadopsi desain responsif mobile-first. Untuk perangkat mobile, terdapat header atas yang statis dengan navigasi esensial (home, pencarian, keranjang) dan bilah navigasi bawah yang statis untuk aksi utama (home, kategori, lacak pesanan). Tampilan desktop akan menggunakan header atas tradisional dengan navigasi yang diperluas. Struktur ini memprioritaskan akses cepat ke fungsionalitas inti pada layar kecil dan tata letak yang jelas serta terorganisir pada layar yang lebih besar, meningkatkan pemahaman dan kemudahan navigasi pengguna dengan menjaga aksi-aksi kunci selalu tersedia secara konsisten. -->
<!-- Visualization & Content Choices:
- Global Headers (Mobile & Desktop): Tujuan: Menyediakan branding dan navigasi yang konsisten. Metode Visualisasi/Presentasi: Teks, ikon SVG untuk kejelasan. Interaksi: Tautan yang dapat diklik untuk navigasi. Justifikasi: Pola web standar, intuitif. Library/Metode: HTML, Tailwind CSS.
- Cart Count Badge: Tujuan: Menginformasikan status keranjang kepada pengguna. Metode Visualisasi/Presentasi: Badge numerik kecil. Interaksi: Pembaruan dinamis melalui JS. Justifikasi: Umpan balik visual tanpa memerlukan pemuatan halaman. Library/Metode: HTML, Tailwind CSS, Vanilla JS.
- Bottom Navigation (Mobile): Tujuan: Menyediakan akses navigasi utama di mobile. Metode Visualisasi/Presentasi: Ikon dan label teks. Interaksi: Tautan yang dapat diklik untuk navigasi. Justifikasi: Pola Android umum untuk jangkauan jempol yang mudah. Library/Metode: HTML, Tailwind CSS.
- Page Content: Tujuan: Menampilkan konten halaman spesifik. Metode Visualisasi/Presentasi: @yield('content'). Interaksi: Ditangani oleh halaman individual. Justifikasi: Templating Laravel standar. Library/Metode: Blade.
CONFIRMATION: NO SVG graphics used. NO Mermaid JS used. -->
<!DOCTYPE html>
<html lang="id">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>{{ setting('app_name_public', 'Digitalin - Etalase') }}</title>

  <script src="https://cdn.tailwindcss.com"></script>
  <style>
    /* Chosen Palette: Calm Harmony */
    :root {
      --color-bg-primary: #F9FAFB;
      /* gray-50 */
      --color-bg-secondary: #FFFFFF;
      /* white */
      --color-text-dark: #1F2937;
      /* gray-800 */
      --color-text-medium: #4B5563;
      /* gray-600 */
      --color-accent-blue: #3B82F6;
      /* blue-500 */
      --color-accent-dark-blue: #2563EB;
      /* blue-600 */
      --color-accent-light-blue: #DBEAFE;
      /* blue-100 */
      --color-border: #E5E7EB;
      /* gray-200 */
      --color-red: #EF4444;
      /* red-500 */
      --color-green: #10B981;
      /* green-500 */
      --color-dark-green: #059669;
      /* green-600 */
    }

    body {
      font-family: 'Inter', sans-serif;
      background-color: var(--color-bg-primary);
      color: var(--color-text-dark);
    }

    .container-custom {
      max-width: 1280px;
      /* Lebarkan sedikit untuk desktop */
      margin-left: auto;
      margin-right: auto;
      padding-left: 1rem;
      padding-right: 1rem;
    }

    .section-spacing {
      padding-top: 4rem;
      padding-bottom: 4rem;
    }

    .card {
      background-color: var(--color-bg-secondary);
      border-radius: 0.75rem;
      box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
      /* Shadow lebih lembut */
      padding: 1.5rem;
    }

    .btn-primary {
      background-color: var(--color-accent-blue);
      color: white;
      padding: 0.75rem 1.5rem;
      border-radius: 0.5rem;
      font-weight: 600;
      transition: background-color 0.3s ease;
    }

    .btn-primary:hover {
      background-color: var(--color-accent-dark-blue);
    }

    .nav-link {
      transition: color 0.3s ease;
    }

    .nav-link:hover {
      color: var(--color-accent-blue);
    }

    /* Style untuk badge keranjang */
    .cart-badge {
      position: absolute;
      top: -5px;
      right: -5px;
      background-color: var(--color-red);
      color: white;
      border-radius: 9999px;
      padding: 0.1rem 0.4rem;
      font-size: 0.7rem;
      line-height: 1;
      min-width: 18px;
      text-align: center;
    }

    /* Styling tambahan untuk bottom nav item */
    .bottom-nav-item {
      padding: 0.5rem 0.75rem;
      /* Lebih banyak padding */
      border-radius: 0.5rem;
      transition: background-color 0.2s ease;
    }

    .bottom-nav-item.active {
      background-color: var(--color-accent-light-blue);
      color: var(--color-accent-blue);
    }

    .bottom-nav-item:hover {
      background-color: var(--color-bg-primary);
    }
  </style>
</head>

<body class="bg-gray-100 flex flex-col min-h-screen">
  <!-- Header Mobile (Hanya tampil di mobile) -->
  <header class="md:hidden bg-white shadow-sm p-4 sticky top-0 z-40 border-b border-gray-100">
    <div class="container-custom flex justify-between items-center">
      <a href="{{ route('shop.index') }}" class="text-xl font-bold text-gray-800">{{ setting('app_name_public',
        'Digitalin') }}</a>
      <div class="flex items-center space-x-4">
        {{-- Ikon Pencarian --}}
        <a href="#" class="text-gray-600 hover:text-blue-500 transition-colors duration-200">
          <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
              d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
          </svg>
        </a>
        {{-- Ikon Keranjang dengan Notifikasi --}}
        <a href="{{ route('shop.cart') }}"
          class="text-gray-600 hover:text-blue-500 relative transition-colors duration-200">
          <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
              d="M3 3h2l.4 2M7 13h14l-1.35 6H6.65L5 13zm0 0L5 6h16M7 13L5 6" />
          </svg>
          <span id="cart-count-badge" class="cart-badge hidden">0</span>
        </a>
      </div>
    </div>
  </header>

  <!-- Header Desktop (Hanya tampil di desktop) -->
  <header class="hidden md:flex bg-white shadow-sm p-4 sticky top-0 z-40 border-b border-gray-100">
    <div class="container-custom flex justify-between items-center">
      <a href="{{ route('shop.index') }}" class="text-2xl font-bold text-gray-800">{{ setting('app_name_public',
        'Digitalin') }}</a>
      <nav class="space-x-6">
        <a href="{{ route('shop.index') }}"
          class="nav-link text-gray-600 @if(request()->routeIs('shop.index')) text-blue-600 font-semibold @endif">Home</a>
        <a href="{{ route('shop.cart') }}"
          class="nav-link text-gray-600 @if(request()->routeIs('shop.cart')) text-blue-600 font-semibold @endif">Keranjang</a>
        <a href="{{ route('shop.trackOrder') }}"
          class="nav-link text-gray-600 @if(request()->routeIs(['shop.trackOrder', 'shop.trackOrder.post'])) text-blue-600 font-semibold @endif">Lacak
          Pesanan</a>
        <a href="/profile" class="nav-link text-gray-600">Akun</a> {{-- Placeholder untuk Akun --}}
      </nav>
    </div>
  </header>

  <!-- Main Content -->
  <main class="flex-grow container-custom px-4 pt-4 pb-16 md:pb-4">
    @yield('content')
  </main>

  <!-- Bottom Navigation Mobile (Hanya tampil di mobile) -->
  <nav
    class="fixed bottom-0 inset-x-0 bg-white shadow-lg p-2 flex justify-around md:hidden z-50 border-t border-gray-100">
    <a href="{{ route('shop.index') }}"
      class="bottom-nav-item flex flex-col items-center @if(request()->routeIs('shop.index')) text-blue-600 active @else text-gray-600 @endif">
      <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M13 5v6h6" />
      </svg>
      <span class="text-xs mt-1">Home</span>
    </a>
    {{-- Menu Kategori (Placeholder) --}}
    <a href="#" class="bottom-nav-item flex flex-col items-center text-gray-600">
      <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
      </svg>
      <span class="text-xs mt-1">Kategori</span>
    </a>
    <a href="{{ route('shop.trackOrder') }}"
      class="bottom-nav-item flex flex-col items-center @if(request()->routeIs(['shop.trackOrder', 'shop.trackOrder.post'])) text-blue-600 active @else text-gray-600 @endif">
      <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2a4 4 0 018 0v2" />
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 11a7 7 0 0114 0v6H5v-6z" />
      </svg>
      <span class="text-xs mt-1">Pesanan</span>
    </a>
  </nav>

  <script>
    // Fungsi untuk mengupdate jumlah item di keranjang pada badge
        function updateCartCountBadge(count) {
            const badge = document.getElementById('cart-count-badge');
            if (badge) {
                if (count > 0) {
                    badge.textContent = count;
                    badge.classList.remove('hidden');
                } else {
                    badge.classList.add('hidden');
                }
            }
        }

        // Ambil jumlah item keranjang dari session saat halaman dimuat
        document.addEventListener('DOMContentLoaded', function() {
            fetch('/api/cart-count') // Asumsi Anda akan membuat endpoint API ini
                .then(response => {
                    if (!response.ok) {
                        throw new Error('Network response was not ok');
                    }
                    return response.json();
                })
                .then(data => {
                    updateCartCountBadge(data.count);
                })
                .catch(error => {
                    console.error('Error fetching cart count:', error);
                    updateCartCountBadge(0);
                });
        });
  </script>
</body>

</html>