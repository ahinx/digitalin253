@extends('layouts.app')
@section('content')
<div class="max-w-2xl mx-auto p-4">
    <h1 class="text-xl font-bold mb-4">Keranjang Belanja</h1>
    @if(count($items))
    @php $total = 0; @endphp
    <div class="space-y-4">
        @foreach($items as $item)
        @php $price = $item['variant']->price ?? $item['product']->price; $total += $price; @endphp
        <div class="flex justify-between border p-2">
            <div>
                <strong>{{ $item['product']->name }}</strong>
                @if($item['variant']) <p>Varian: {{ $item['variant']->name }}</p> @endif
            </div>
            <div>Rp{{ number_format($price, 0, ',', '.') }}</div>
        </div>
        @endforeach
    </div>

    <p class="mt-4 text-right font-bold">Total: Rp{{ number_format($total, 0, ',', '.') }}</p>

    <form id="checkout-form" class="space-y-2 mt-4">
        @csrf
        <input type="text" name="name" class="w-full border p-2" placeholder="Nama" required>
        <input type="email" name="email" class="w-full border p-2" placeholder="Email" required>
        <input type="text" name="phone" class="w-full border p-2" placeholder="No. HP" required>
        <button type="submit" class="bg-green-600 text-white px-4 py-2 rounded">Bayar Sekarang</button>
    </form>

    <script src="https://app.sandbox.midtrans.com/snap/snap.js"
        data-client-key="{{ config('services.midtrans.client_key') }}"></script>
    <script>
        document.getElementById('checkout-form').addEventListener('submit', async function(e) {
                e.preventDefault();
            
                const formData = new FormData(this);
            
                const response = await fetch('{{ secure_url(route('shop.checkout', [], false)) }}', {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                        'Accept': 'application/json'
                    },
                    body: formData
                });
            
                const data = await response.json();
            
                if (data.snapToken) {
                    snap.pay(data.snapToken, {
                        onSuccess: () => window.location.href = '/thank-you?order_id=' + data.orderId,
                        onError: () => alert('Pembayaran gagal.'),
                        onPending: () => alert('Pembayaran belum selesai.'),
                    });
                } else {
                    alert(data.error || 'Checkout gagal');
                }
            });
    </script>

    @else
    <p>Keranjang kosong.</p>
    @endif
</div>
@endsection