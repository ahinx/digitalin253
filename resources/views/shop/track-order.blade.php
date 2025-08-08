@extends('layouts.app')

@section('content')
<style>
    /* Gaya kustom ini tetap di sini karena spesifik untuk komponen di halaman ini */
    /* Gaya body di sini akan ditimpa oleh layouts/app.blade.php */
    .container {
        background-color: #ffffff;
        padding: 2.5rem;
        border-radius: 0.75rem;
        box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
        width: 100%;
        max-width: 3xl;
        /* Tailwind max-w-3xl */
        text-align: center;
        margin-bottom: 2rem;
        margin-left: auto;
        /* Untuk centering */
        margin-right: auto;
        /* Untuk centering */
    }

    .form-group {
        margin-bottom: 1.5rem;
    }

    .input-field {
        width: 100%;
        padding: 0.75rem;
        border: 1px solid #d1d5db;
        /* gray-300 */
        border-radius: 0.375rem;
        /* rounded-md */
        box-shadow: inset 0 1px 2px 0 rgba(0, 0, 0, 0.05);
        /* shadow-sm */
        font-size: 1rem;
    }

    .button-primary {
        background-color: #3b82f6;
        /* blue-500 */
        color: white;
        padding: 0.75rem 1.5rem;
        border-radius: 0.5rem;
        font-weight: 600;
        transition: background-color 0.3s ease;
        cursor: pointer;
        border: none;
        width: 100%;
    }

    .button-primary:hover {
        background-color: #2563eb;
        /* blue-600 */
    }

    .order-card {
        background-color: #ffffff;
        padding: 1.5rem;
        border-radius: 0.75rem;
        box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
        margin-bottom: 1rem;
        text-align: left;
        border-left: 5px solid;
    }

    .order-card.pending {
        border-color: #9ca3af;
        /* gray-400 */
    }

    .order-card.paid {
        border-color: #10b981;
        /* emerald-500 */
    }

    .order-card.expired {
        border-color: #f59e0b;
        /* amber-500 */
    }

    .order-card.cancelled {
        border-color: #ef4444;
        /* red-500 */
    }

    .order-card.denied {
        border-color: #dc2626;
        /* red-600 */
    }

    .badge {
        display: inline-flex;
        align-items: center;
        padding: 0.25rem 0.75rem;
        border-radius: 9999px;
        /* full */
        font-size: 0.875rem;
        font-weight: 600;
        line-height: 1;
        text-transform: uppercase;
        letter-spacing: 0.05em;
    }

    .badge-gray {
        background-color: #e5e7eb;
        color: #4b5563;
    }

    /* gray-200, gray-700 */
    .badge-green {
        background-color: #d1fae5;
        color: #059669;
    }

    /* green-100, green-700 */
    .badge-yellow {
        background-color: #fef3c7;
        color: #b45309;
    }

    /* yellow-100, yellow-800 */
    .badge-red {
        background-color: #fee2e2;
        color: #dc2626;
    }

    /* red-100, red-600 */
    .link-style {
        color: #3b82f6;
        text-decoration: underline;
        font-weight: 500;
    }
</style>

<div class="container">
    <h1 class="text-3xl font-bold text-gray-800 mb-6">Lacak Pesanan Anda</h1>

    <form action="{{ route('shop.trackOrder.post') }}" method="POST" class="w-full">
        @csrf
        <div class="form-group">
            <label for="phone" class="block text-gray-700 text-sm font-bold mb-2 text-left">
                Nomor WhatsApp (misal: 628123456789)
            </label>
            <input type="text" id="phone" name="phone" value="{{ old('phone', $phone) }}" class="input-field"
                placeholder="Masukkan nomor WhatsApp Anda" required>
        </div>
        <button type="submit" class="button-primary">Cari Pesanan</button>
    </form>
</div>

@if($searchPerformed)
<div class="w-full max-w-3xl mx-auto">
    <h2 class="text-2xl font-bold text-gray-800 mb-4 text-center">Hasil Pencarian</h2>

    @if($orders->isEmpty())
    <div class="bg-yellow-100 border-l-4 border-yellow-500 text-yellow-700 p-4 rounded-md" role="alert">
        <p class="font-bold">Tidak Ditemukan</p>
        <p>Tidak ada pesanan yang ditemukan dengan nomor WhatsApp tersebut.</p>
    </div>
    @else
    @foreach($orders as $order)
    <div class="order-card {{ $order->status }}">
        <h3 class="text-xl font-semibold text-gray-800 mb-2">
            Pesanan #{{ $order->id }}
            <span class="badge @if($order->status === 'pending') badge-gray
                                            @elseif($order->status === 'paid') badge-green
                                            @elseif($order->status === 'expired') badge-yellow
                                            @elseif($order->status === 'cancelled' || $order->status === 'denied') badge-red
                                            @else badge-gray @endif">
                {{ ucfirst($order->status) }}
            </span>
        </h3>
        <p class="text-gray-600 mb-1">Nama Pembeli: {{ $order->buyer_name }}</p>
        <p class="text-gray-600 mb-1">Email: {{ $order->email }}</p>
        <p class="text-gray-600 mb-1">Total Harga: Rp{{ number_format($order->total_price, 0, ',', '.') }}</p>
        <p class="text-gray-600 mb-3">Tanggal Pesan: {{ $order->created_at->format('d M Y H:i') }}</p>

        @if($order->status === 'pending')
        <p class="text-blue-600 font-medium">Pesanan Anda masih menunggu pembayaran.</p>
        @if(isset($snapTokens[$order->id]) && $snapTokens[$order->id])
        <button type="button" class="button-primary mt-2"
            onclick="payWithSnap('{{ $snapTokens[$order->id] }}', {{ $order->id }})">
            Lanjutkan Pembayaran
        </button>
        @else
        <p class="text-red-500 text-sm mt-2">Gagal memuat opsi pembayaran. Silakan coba lagi nanti.</p>
        @endif
        @elseif($order->status === 'paid')
        <p class="text-green-600 font-medium">Pembayaran Anda telah berhasil!</p>
        @if($order->magic_link_token)
        <a href="{{ url('/magic-link/' . $order->magic_link_token) }}" class="link-style block mt-2">
            Unduh Produk Anda (Magic Link)
        </a>
        @else
        <a href="{{ url('/thank-you?order_id=' . $order->id) }}" class="link-style block mt-2">
            Lihat Detail Pesanan
        </a>
        @endif
        @else
        <p class="text-gray-600 font-medium">Status pesanan: {{ ucfirst($order->status) }}.</p>
        <a href="{{ url('/thank-you?order_id=' . $order->id) }}" class="link-style block mt-2">
            Lihat Detail Pesanan
        </a>
        @endif
    </div>
    @endforeach
    @endif
</div>
@endif

{{-- Midtrans Snap JS --}}
{{-- Pastikan ini dimuat setelah elemen HTML tempat tombol Bayar Sekarang berada --}}
<script type="text/javascript" src="https://app.sandbox.midtrans.com/snap/snap.js"
    data-client-key="{{ config('services.midtrans.client_key') }}">
</script>

<script type="text/javascript">
    function payWithSnap(snapToken, orderId) {
        if (snapToken) {
            window.snap.pay(snapToken, {
                onSuccess: function(result){
                    console.log('Payment success:', result);
                    window.location.href = "{{ url('/thank-you?order_id=') }}" + orderId;
                },
                onPending: function(result){
                    console.log('Payment pending:', result);
                    window.location.href = "{{ url('/thank-you?order_id=') }}" + orderId;
                },
                onError: function(result){
                    console.log('Payment failed:', result);
                    window.location.href = "{{ url('/thank-you?order_id=') }}" + orderId;
                },
                onClose: function(){
                    console.log('Payment popup closed without finishing.');
                    // Opsional: refresh halaman atau tampilkan pesan
                }
            });
        } else {
            console.error('Snap Token tidak tersedia untuk Order ID:', orderId);
        }
    }

    // Logika untuk memicu Snap secara otomatis saat halaman dimuat
    document.addEventListener('DOMContentLoaded', function() {
        const autoSnapOrderId = "{{ $autoSnapOrderId ?? '' }}"; // Ambil autoSnapOrderId dari Laravel
        if (autoSnapOrderId) {
            // Cari snapToken yang sesuai dengan autoSnapOrderId
            const snapTokens = {!! json_encode($snapTokens) !!}; // Pastikan $snapTokens di-encode dengan benar
            const targetSnapToken = snapTokens[autoSnapOrderId];

            if (targetSnapToken) {
                // Panggil fungsi payWithSnap setelah halaman diload dan Snap JS siap
                // Memberi sedikit delay untuk memastikan Snap JS sepenuhnya dimuat
                setTimeout(() => {
                    payWithSnap(targetSnapToken, autoSnapOrderId);
                }, 500); // Delay 500ms
            } else {
                console.error('Snap Token tidak ditemukan untuk autoSnapOrderId:', autoSnapOrderId);
            }
        }
    });
</script>
@endsection