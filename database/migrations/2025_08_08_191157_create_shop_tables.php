<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up(): void
    {
        // Tabel 'app_settings' untuk konfigurasi dinamis
        Schema::create('app_settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique()->comment('Kunci unik untuk setting (contoh: midtrans_server_key)');
            $table->text('value')->nullable()->comment('Nilai setting');
            $table->timestamps();
        });

        // Tabel 'products'
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('name')->comment('Nama produk');
            $table->text('description')->nullable()->comment('Deskripsi produk');
            $table->enum('type', ['simple', 'variant'])->comment('Tipe produk: simple (tanpa varian), variant (dengan varian)');
            $table->string('main_image')->nullable()->comment('Path gambar utama produk');
            $table->decimal('price', 12, 0)->nullable()->comment('Harga produk (tanpa desimal)'); // <<< DECIMAL (X,0)
            $table->decimal('discount_price', 12, 0)->nullable()->comment('Harga diskon produk (tanpa desimal)'); // <<< DECIMAL (X,0)

            // Kolom SEO
            $table->string('seo_title')->nullable();
            $table->text('seo_description')->nullable();
            $table->string('seo_keywords')->nullable();
            $table->string('seo_image_alt')->nullable();

            // Kolom untuk produk digital/downloadable
            $table->enum('downloadable_type', ['file', 'link'])->nullable()->comment('Tipe unduhan: file (lokal) atau link (eksternal)');
            $table->string('file_path')->nullable()->comment('Path file jika downloadable_type adalah file');
            $table->string('external_url')->nullable()->comment('URL eksternal jika downloadable_type adalah link');
            $table->string('download_url')->nullable()->comment('URL unduhan langsung (opsional, bisa untuk magic link)'); // Kolom tambahan dari migrasi Anda

            $table->timestamps();
            $table->softDeletes()->comment('Timestamp untuk soft delete');
        });

        // Tabel 'product_variants'
        Schema::create('product_variants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->onDelete('cascade')->comment('Foreign key ke tabel products');
            $table->string('name')->comment('Nama varian (contoh: Warna Merah, Ukuran L)');
            $table->decimal('price', 12, 0)->comment('Harga varian (tanpa desimal)'); // <<< DECIMAL (X,0)
            $table->string('image')->nullable()->comment('Path gambar varian');

            // Kolom untuk varian produk digital/downloadable
            $table->enum('downloadable_type', ['file', 'link'])->comment('Tipe unduhan varian');
            $table->string('file_path')->nullable()->comment('Path file varian');
            $table->string('external_url')->nullable()->comment('URL eksternal varian');

            $table->timestamps();
        });

        // Tabel 'vouchers'
        Schema::create('vouchers', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique()->comment('Kode voucher unik');
            $table->enum('discount_type', ['fixed', 'percent'])->comment('Tipe diskon: fixed (jumlah tetap) atau percent (persentase)');
            $table->decimal('value', 10, 0)->comment('Nilai diskon (tanpa desimal)'); // <<< DECIMAL (X,0)
            $table->dateTime('expires_at')->nullable()->comment('Tanggal kadaluarsa voucher');
            $table->timestamps();
        });

        // Tabel 'banners'
        Schema::create('banners', function (Blueprint $table) {
            $table->id();
            $table->string('image')->comment('Path gambar banner');
            $table->string('alt_text')->nullable()->comment('Alt text untuk gambar banner');
            $table->string('url')->nullable()->comment('URL tujuan saat banner diklik');
            $table->timestamps();
        });

        // Tabel 'reviews'
        Schema::create('reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->onDelete('cascade')->comment('Foreign key ke tabel products');
            $table->tinyInteger('rating')->comment('Rating produk (1-5)');
            $table->text('content')->nullable()->comment('Isi review');
            $table->timestamps();
        });

        // Tabel 'orders'
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->string('buyer_name')->comment('Nama pembeli');
            $table->string('phone')->comment('Nomor telepon pembeli');
            $table->string('email')->nullable()->comment('Email pembeli');
            $table->string('magic_link_token')->unique()->comment('Token unik untuk magic link order');
            $table->string('tracking_key', 6)->nullable()->comment('Kunci 6 digit untuk pelacakan pesanan oleh pembeli');
            $table->enum('status', ['pending', 'paid', 'expired', 'cancelled', 'denied', 'challenge'])->default('pending')->comment('Status pembayaran order'); // Menambahkan status 'cancelled', 'denied', 'challenge'
            $table->decimal('total_price', 10, 0)->default(0)->comment('Total harga order (tanpa desimal)'); // <<< DECIMAL (X,0)
            $table->json('payment_info')->nullable()->comment('Payload JSON dari notifikasi pembayaran (contoh: Midtrans)'); // Kolom tambahan dari migrasi Anda
            $table->timestamps();
            $table->softDeletes()->comment('Timestamp untuk soft delete');
        });

        // Tabel 'order_items'
        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->onDelete('cascade')->comment('Foreign key ke tabel orders');
            $table->foreignId('product_id')->constrained()->onDelete('cascade')->comment('Foreign key ke tabel products');
            $table->foreignId('product_variant_id')->nullable()->constrained()->onDelete('cascade')->comment('Foreign key ke tabel product_variants');
            $table->decimal('price', 10, 0)->comment('Harga item saat order dibuat (tanpa desimal)'); // <<< DECIMAL (X,0)
            $table->integer('quantity')->default(1)->comment('Kuantitas item yang dibeli'); // <<< Kolom quantity

            // PERBAIKAN: Buat kolom deliverable_type dan deliverable_id secara eksplisit
            // dan tambahkan komentar pada masing-masing.
            $table->string('deliverable_type')->nullable()->comment('Tipe model untuk item yang dapat di-deliver (polymorphic relation)');
            $table->unsignedBigInteger('deliverable_id')->nullable()->comment('ID dari model yang dapat di-deliver (polymorphic relation)');
            $table->index(['deliverable_type', 'deliverable_id']); // Tambahkan index untuk polymorphic relation

            $table->timestamps();
        });

        // Tabel 'notifications' (untuk notifikasi internal atau log pengiriman notifikasi)
        Schema::create('notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->onDelete('cascade')->comment('Foreign key ke tabel users (opsional)');
            $table->text('content')->comment('Isi notifikasi');
            $table->enum('status', ['sent', 'failed'])->default('sent')->comment('Status pengiriman notifikasi');
            $table->timestamp('sent_at')->nullable()->comment('Waktu notifikasi dikirim');
            $table->timestamps();
        });

        // Catatan: Tabel 'users', 'cache', 'cache_locks', 'failed_jobs', 'migrations', 'password_reset_tokens', 'sessions', 'jobs', 'job_batches'
        // biasanya dibuat oleh migrasi Laravel standar atau Artisan commands.
        // Pastikan migrasi standar Laravel sudah ada dan dijalankan terlebih dahulu.
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down(): void
    {
        // Urutan drop tabel harus terbalik dari urutan create
        Schema::dropIfExists('notifications');
        Schema::dropIfExists('order_items');
        Schema::dropIfExists('orders');
        Schema::dropIfExists('reviews');
        Schema::dropIfExists('banners');
        Schema::dropIfExists('vouchers');
        Schema::dropIfExists('product_variants');
        Schema::dropIfExists('products');
        Schema::dropIfExists('app_settings'); // Drop app_settings terakhir jika dibuat pertama
    }
};
