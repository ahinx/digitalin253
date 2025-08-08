<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pembayaran Pesanan #{{ $order->id }}</title>
    <!-- Tailwind CSS (jika Anda menggunakannya di project Anda) -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- Midtrans Snap JS (Sandbox) -->
    <!-- Ganti dengan URL produksi jika mode adalah 'production' -->
    <script type="text/javascript" src="https://app.sandbox.midtrans.com/snap/snap.js"
        data-client-key="{{ config('services.midtrans.client_key') }}">
    </script>
    <style>
        body {
            font-family: 'Inter', sans-serif;
            background-color: #f3f4f6;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            margin: 0;
        }

        .container {
            background-color: #ffffff;
            padding: 2.5rem;
            /* p-10 */
            border-radius: 0.75rem;
            /* rounded-xl */
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
            /* shadow-xl */
            width: 100%;
            max-width: 28rem;
            /* max-w-sm */
            text-align: center;
        }

        .button-primary {
            background-color: #3b82f6;
            /* blue-500 */
            color: white;
            padding: 0.75rem 1.5rem;
            /* py-3 px-6 */
            border-radius: 0.5rem;
            /* rounded-lg */
            font-weight: 600;
            /* font-semibold */
            transition: background-color 0.3s ease;
            cursor: pointer;
            border: none;
            width: 100%;
            margin-top: 1.5rem;
            /* mt-6 */
        }

        .button-primary:hover {
            background-color: #2563eb;
            /* blue-600 */
        }
    </style>
</head>

<body>
    <div class="container">
        <h1 class="text-3xl font-bold text-gray-800 mb-4">Pembayaran Pesanan Anda</h1>
        <p class="text-lg text-gray-600 mb-2">Order ID: <span class="font-semibold">#{{ $order->id }}</span></p>
        <p class="text-xl font-bold text-blue-600 mb-6">Total: Rp{{ number_format($order->total_price, 0, ',', '.') }}
        </p>

        <button id="pay-button" class="button-primary">Bayar Sekarang</button>

        <div id="snap-container" class="mt-4">
            <!-- Midtrans Snap akan dirender di sini -->
        </div>

        <p class="text-sm text-gray-500 mt-4">Anda akan diarahkan ke halaman pembayaran Midtrans.</p>
    </div>

    <script type="text/javascript">
        document.addEventListener('DOMContentLoaded', function() {
            const payButton = document.getElementById('pay-button');
            const snapToken = '{{ $snapToken }}';

            payButton.addEventListener('click', function () {
                if (snapToken) {
                    window.snap.pay(snapToken, {
                        onSuccess: function(result){
                            console.log('Payment success:', result);
                            // PERBAIKAN: Menggunakan url() untuk redirect ke halaman thank you
                            window.location.href = "{{ url('/thank-you?order_id=' . $order->id) }}";
                        },
                        onPending: function(result){
                            console.log('Payment pending:', result);
                            // PERBAIKAN: Menggunakan url() untuk redirect ke halaman thank you (opsional)
                            window.location.href = "{{ url('/thank-you?order_id=' . $order->id) }}";
                        },
                        onError: function(result){
                            console.log('Payment failed:', result);
                            // PERBAIKAN: Menggunakan url() untuk redirect ke halaman thank you (opsional)
                            window.location.href = "{{ url('/thank-you?order_id=' . $order->id) }}";
                        },
                        onClose: function(){
                            console.log('Payment popup closed without finishing.');
                            // Opsional: tampilkan pesan di halaman atau redirect
                        }
                    });
                } else {
                    console.error('Snap Token tidak tersedia.');
                    // Tampilkan pesan error di UI tanpa alert
                }
            });
        });
    </script>
</body>

</html>