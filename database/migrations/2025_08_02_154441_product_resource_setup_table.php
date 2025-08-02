<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->enum('type', ['simple', 'variant']);
            $table->string('main_image')->nullable();
            $table->decimal('price', 12, 2)->nullable();
            $table->decimal('discount_price', 12, 2)->nullable();
            $table->string('seo_title')->nullable();
            $table->text('seo_description')->nullable();
            $table->string('seo_keywords')->nullable();
            $table->string('seo_image_alt')->nullable();
            $table->enum('downloadable_type', ['file', 'link'])->nullable();
            $table->string('file_path')->nullable();
            $table->string('external_url')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('product_variants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->onDelete('cascade');
            $table->string('name');
            $table->decimal('price', 12, 2);
            $table->string('image')->nullable();
            $table->enum('downloadable_type', ['file', 'link']);
            $table->string('file_path')->nullable();
            $table->string('external_url')->nullable();
            $table->timestamps();
        });

        Schema::create('vouchers', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->enum('discount_type', ['fixed', 'percent']);
            $table->decimal('value', 10, 2);
            $table->dateTime('expires_at')->nullable();
            $table->timestamps();
        });

        Schema::create('banners', function (Blueprint $table) {
            $table->id();
            $table->string('image');
            $table->string('alt_text')->nullable();
            $table->string('url')->nullable();
            $table->timestamps();
        });

        Schema::create('reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->onDelete('cascade');
            $table->tinyInteger('rating');
            $table->text('content')->nullable();
            $table->timestamps();
        });

        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->string('buyer_name');
            $table->string('phone');
            $table->string('email')->nullable();
            $table->string('magic_link_token')->unique();
            $table->enum('status', ['pending', 'paid', 'expired'])->default('pending');
            $table->timestamps();
        });

        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->onDelete('cascade');
            $table->foreignId('product_id')->constrained()->onDelete('cascade');
            $table->foreignId('product_variant_id')->nullable()->constrained()->onDelete('cascade');
            $table->nullableMorphs('deliverable');
            $table->timestamps();
        });

        Schema::create('notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->onDelete('cascade');
            $table->text('content');
            $table->enum('status', ['sent', 'failed'])->default('sent');
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notifications');
        Schema::dropIfExists('order_items');
        Schema::dropIfExists('orders');
        Schema::dropIfExists('reviews');
        Schema::dropIfExists('banners');
        Schema::dropIfExists('vouchers');
        Schema::dropIfExists('product_variants');
        Schema::dropIfExists('products');
    }
};
