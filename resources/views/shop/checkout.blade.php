<h1>Checkout</h1>

<p><strong>Nama:</strong> {{ $order->buyer_name }}</p>
<p><strong>Email:</strong> {{ $order->email }}</p>
<p><strong>HP:</strong> {{ $order->phone }}</p>

<h3>Produk yang Dibeli:</h3>
<ul>
    @foreach ($order->items as $item)
    <li>
        {{ $item->product->name }}
        @if($item->variant)
        - {{ $item->variant->name }} (Rp{{ number_format($item->variant->price, 0, ',', '.') }})
        @else
        (Rp{{ number_format($item->product->price, 0, ',', '.') }})
        @endif
    </li>
    @endforeach
</ul>

<hr>

<!-- Tombol bayar sekarang -->
<button id="pay-button" style="padding: 10px 20px; background-color: green; color: white;">
    Bayar Sekarang
</button>

<!-- Midtrans Snap -->
<script src="https://app.sandbox.midtrans.com/snap/snap.js"
    data-client-key="{{ config('services.midtrans.client_key') }}"></script>
<script type="text/javascript">
    document.getElementById('pay-button').addEventListener('click', function () {
        snap.pay('{{ $snapToken }}');
    });
</script>