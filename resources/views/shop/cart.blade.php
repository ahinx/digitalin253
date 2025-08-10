@extends('layouts.app')
@section('content')
<div class="max-w-2xl mx-auto p-4">
    <h1 class="text-xl font-bold mb-4">Keranjang Belanja</h1>
    @if(count($items))
    <div class="space-y-4">
        @foreach($items as $item)
        <div class="flex justify-between items-center border p-2 rounded-md shadow-sm">
            <div>
                <strong>{{ $item['product']->name }}</strong>
                @if($item['variant']) <p class="text-sm text-gray-600">Varian: {{ $item['variant']->name }}</p> @endif
                <p class="text-sm text-gray-700">Kuantitas: {{ $item['quantity'] }}</p>
            </div>
            <div class="text-right">
                <p>Rp{{ number_format($item['price'], 0, ',', '.') }}</p>
                <p class="font-semibold">Subtotal: Rp{{ number_format($item['subtotal'], 0, ',', '.') }}</p>
            </div>
        </div>
        @endforeach
    </div>

    <div class="mt-6 border-t pt-4">
        <p class="text-right font-bold text-lg">Subtotal Keranjang: <span id="subtotal_display">Rp{{
                number_format($subtotalPrice, 0, ',', '.') }}</span></p>

        {{-- Bagian Voucher --}}
        <div class="mt-4 p-4 border rounded-md bg-gray-50">
            <h2 class="font-semibold mb-2">Punya Kode Voucher?</h2>
            <div class="flex space-x-2">
                <input type="text" id="voucher_code_input" class="flex-grow border p-2 rounded-md"
                    placeholder="Masukkan Kode Voucher">
                <button type="button" onclick="applyVoucher()"
                    class="bg-blue-500 text-white px-4 py-2 rounded-md hover:bg-blue-600">Terapkan</button>
            </div>
            <div id="voucher_message" class="mt-2 text-sm"></div>

            @if($appliedVoucher)
            <div id="applied_voucher_display"
                class="mt-3 p-2 bg-green-100 border border-green-300 text-green-800 rounded-md flex justify-between items-center">
                <span>Voucher *{{ $appliedVoucher['code'] }}* diterapkan!</span>
                <button type="button" onclick="removeVoucher()"
                    class="text-red-600 hover:text-red-800 font-semibold text-sm">Hapus</button>
            </div>
            @else
            <div id="applied_voucher_display" style="display: none;"></div>
            @endif
        </div>

        <p class="mt-2 text-right font-bold text-red-500 text-lg">Diskon Voucher: - <span id="discount_display">Rp{{
                number_format($discountAmount, 0, ',', '.') }}</span></p>
        <p class="mt-2 text-right font-bold text-2xl">Total Pembayaran: <span id="final_total_display">Rp{{
                number_format($finalPrice, 0, ',', '.') }}</span></p>
    </div>

    <form id="checkout-form" class="space-y-2 mt-6">
        @csrf
        <input type="text" name="name" class="w-full border p-2 rounded-md" placeholder="Nama" required>
        <input type="email" name="email" class="w-full border p-2 rounded-md" placeholder="Email" required>
        <input type="text" name="phone" class="w-full border p-2 rounded-md" placeholder="No. HP" required>
        {{-- Input tersembunyi untuk menyimpan ID voucher yang diterapkan --}}
        <input type="hidden" name="voucher_id" id="hidden_voucher_id" value="{{ $appliedVoucher['id'] ?? '' }}">
        <button type="submit" class="bg-green-600 text-white px-4 py-2 rounded-md w-full hover:bg-green-700">Bayar
            Sekarang</button>
    </form>

    <script src="https://app.sandbox.midtrans.com/snap/snap.js"
        data-client-key="{{ config('services.midtrans.client_key') }}"></script>
    <script>
        // Fungsi untuk memformat angka menjadi format mata uang Rupiah
        function formatRupiah(angka) {
            var reverse = angka.toString().split('').reverse().join(''),
                ribuan = reverse.match(/\d{1,3}/g);
            ribuan = ribuan.join('.').split('').reverse().join('');
            return 'Rp' + ribuan;
        }

        async function applyVoucher() {
            const voucherCode = document.getElementById('voucher_code_input').value;
            const voucherMessage = document.getElementById('voucher_message');
            const subtotalPrice = {{ $subtotalPrice }}; // Ambil subtotal dari Blade

            if (!voucherCode) {
                voucherMessage.className = 'mt-2 text-sm text-red-500';
                voucherMessage.textContent = 'Kode voucher tidak boleh kosong.';
                return;
            }

            voucherMessage.className = 'mt-2 text-sm text-gray-500';
            voucherMessage.textContent = 'Menerapkan voucher...';

            try {
                const response = await fetch('{{ secure_url(route('shop.applyVoucher', [], false)) }}', {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                        'Content-Type': 'application/json',
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify({ voucher_code: voucherCode })
                });

                const data = await response.json();

                if (data.success) {
                    voucherMessage.className = 'mt-2 text-sm text-green-600';
                    voucherMessage.textContent = data.message;
                    document.getElementById('discount_display').textContent = formatRupiah(data.discount_amount);
                    document.getElementById('final_total_display').textContent = formatRupiah(data.final_price);
                    document.getElementById('hidden_voucher_id').value = data.voucher_id; // Set hidden input

                    // Tampilkan display voucher yang diterapkan
                    const appliedVoucherDisplay = document.getElementById('applied_voucher_display');
                    appliedVoucherDisplay.style.display = 'flex';
                    appliedVoucherDisplay.className = 'mt-3 p-2 bg-green-100 border border-green-300 text-green-800 rounded-md flex justify-between items-center';
                    appliedVoucherDisplay.innerHTML = `
                        <span>Voucher *${voucherCode}* diterapkan!</span>
                        <button type="button" onclick="removeVoucher()" class="text-red-600 hover:text-red-800 font-semibold text-sm">Hapus</button>
                    `;

                } else {
                    voucherMessage.className = 'mt-2 text-sm text-red-500';
                    voucherMessage.textContent = data.error;
                    // Reset diskon dan total jika voucher gagal
                    document.getElementById('discount_display').textContent = formatRupiah(0);
                    document.getElementById('final_total_display').textContent = formatRupiah(subtotalPrice);
                    document.getElementById('hidden_voucher_id').value = ''; // Clear hidden input
                    document.getElementById('applied_voucher_display').style.display = 'none';
                }
            } catch (error) {
                console.error('Error applying voucher:', error);
                voucherMessage.className = 'mt-2 text-sm text-red-500';
                voucherMessage.textContent = 'Terjadi kesalahan saat menerapkan voucher.';
                // Reset diskon dan total jika ada error
                document.getElementById('discount_display').textContent = formatRupiah(0);
                document.getElementById('final_total_display').textContent = formatRupiah(subtotalPrice);
                document.getElementById('hidden_voucher_id').value = ''; // Clear hidden input
                document.getElementById('applied_voucher_display').style.display = 'none';
            }
        }

        async function removeVoucher() {
            const voucherMessage = document.getElementById('voucher_message');
            const subtotalPrice = {{ $subtotalPrice }};

            voucherMessage.className = 'mt-2 text-sm text-gray-500';
            voucherMessage.textContent = 'Menghapus voucher...';

            try {
                const response = await fetch('{{ secure_url(route('shop.removeVoucher', [], false)) }}', {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                        'Accept': 'application/json'
                    }
                });

                const data = await response.json();

                if (data.success) {
                    voucherMessage.className = 'mt-2 text-sm text-green-600';
                    voucherMessage.textContent = data.message;
                    document.getElementById('discount_display').textContent = formatRupiah(0);
                    document.getElementById('final_total_display').textContent = formatRupiah(subtotalPrice);
                    document.getElementById('hidden_voucher_id').value = ''; // Clear hidden input
                    document.getElementById('voucher_code_input').value = ''; // Clear voucher input
                    document.getElementById('applied_voucher_display').style.display = 'none';
                } else {
                    voucherMessage.className = 'mt-2 text-sm text-red-500';
                    voucherMessage.textContent = data.error || 'Gagal menghapus voucher.';
                }
            } catch (error) {
                console.error('Error removing voucher:', error);
                voucherMessage.className = 'mt-2 text-sm text-red-500';
                voucherMessage.textContent = 'Terjadi kesalahan saat menghapus voucher.';
            }
        }

        document.getElementById('checkout-form').addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            // formData sudah otomatis menyertakan hidden_voucher_id karena ada di dalam form

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
                    onPending: () => window.location.href = '/thank-you?order_id=' + data.orderId, // Redirect ke thank you juga untuk pending
                    onClose: () => console.log('Pembayaran ditutup.') // Jangan alert atau redirect otomatis
                });
            } else {
                // Tampilkan error dari backend
                alert(data.error || 'Checkout gagal');
            }
        });
    </script>

    @else
    <p>Keranjang kosong.</p>
    @endif
</div>
@endsection