<?php
// app/Helpers/ImageHelpers.php

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;


/**
 * Simpan 1 file upload sebagai WebP (tanpa resize).
 * return: relative path (contoh "products/images/xxxx.webp")
 */
if (! function_exists('storeAsWebp')) {
    function storeAsWebp(
        TemporaryUploadedFile $file,
        string $directory,
        string $disk = 'public',
        int $quality = 82
    ): string {
        $data = file_get_contents($file->getRealPath());
        $img = @imagecreatefromstring($data);
        if (! $img) {
            throw new \RuntimeException('Gagal membaca gambar yang diunggah.');
        }

        // Jaga alpha/transparansi
        if (function_exists('imagepalettetotruecolor')) @imagepalettetotruecolor($img);
        imagealphablending($img, true);
        imagesavealpha($img, true);

        $tmp = tempnam(sys_get_temp_dir(), 'webp_') . '.webp';
        if (! imagewebp($img, $tmp, $quality)) {
            imagedestroy($img);
            throw new \RuntimeException('Konversi ke WebP gagal.');
        }
        imagedestroy($img);

        $filename = Str::uuid()->toString() . '.webp';
        $relativePath = trim($directory, '/') . '/' . $filename;

        Storage::disk($disk)->makeDirectory($directory);
        Storage::disk($disk)->put($relativePath, file_get_contents($tmp), 'public');
        @unlink($tmp);

        return $relativePath;
    }
}

/**
 * Simpan banyak file upload sebagai WebP (tanpa resize).
 * return: array of relative paths
 */
if (! function_exists('storeManyAsWebp')) {
    function storeManyAsWebp(
        array $files,
        string $directory,
        string $disk = 'public',
        int $quality = 82
    ): array {
        $saved = [];
        foreach ($files as $file) {
            /** @var TemporaryUploadedFile $file */
            $saved[] = storeAsWebp($file, $directory, $disk, $quality);
        }
        return $saved;
    }
}

/**
 * Resize proporsional untuk thumbnail (helper kecil).
 */
if (! function_exists('resizeGdProportional')) {
    function resizeGdProportional(\GdImage $img, int $targetWidth): \GdImage
    {
        $w = imagesx($img);
        $h = imagesy($img);
        if ($targetWidth <= 0 || $w <= $targetWidth) {
            // Tidak usah resize jika sudah lebih kecil/ sama
            return $img;
        }
        $ratio = $targetWidth / $w;
        $newW = $targetWidth;
        $newH = (int) round($h * $ratio);

        $dst = imagecreatetruecolor($newW, $newH);
        imagealphablending($dst, false);
        imagesavealpha($dst, true);
        imagecopyresampled($dst, $img, 0, 0, 0, 0, $newW, $newH, $w, $h);
        imagedestroy($img);

        return $dst;
    }
}

/**
 * Simpan 1 file sebagai 2 versi WebP: large & thumb.
 * return: ['large' => 'dir/large/xxx.webp', 'thumb' => 'dir/thumb/xxx.webp']
 */
if (! function_exists('storeAsWebpWithThumb')) {
    function storeAsWebpWithThumb(
        TemporaryUploadedFile $file,
        string $directory,
        string $disk = 'public',
        int $largeQuality = 82,
        int $thumbQuality = 75,
        int $thumbWidth = 300
    ): array {
        $data = file_get_contents($file->getRealPath());
        $img = @imagecreatefromstring($data);
        if (! $img) {
            throw new \RuntimeException('Gagal membaca gambar yang diunggah.');
        }

        if (function_exists('imagepalettetotruecolor')) @imagepalettetotruecolor($img);
        imagealphablending($img, true);
        imagesavealpha($img, true);

        $filename = Str::uuid()->toString() . '.webp';
        $dirLarge = trim($directory, '/') . '/large';
        $dirThumb = trim($directory, '/') . '/thumb';

        // === Simpan versi large ===
        $tmpLarge = tempnam(sys_get_temp_dir(), 'webp_') . '.webp';
        if (! imagewebp($img, $tmpLarge, $largeQuality)) {
            imagedestroy($img);
            throw new \RuntimeException('Konversi WebP (large) gagal.');
        }
        $pathLarge = $dirLarge . '/' . $filename;
        Storage::disk($disk)->makeDirectory($dirLarge);
        Storage::disk($disk)->put($pathLarge, file_get_contents($tmpLarge), 'public');
        @unlink($tmpLarge);

        // === Buat & simpan thumb ===
        $thumb = resizeGdProportional($img, $thumbWidth); // imagedestroy($img) sudah dilakukan di sini
        $tmpThumb = tempnam(sys_get_temp_dir(), 'webp_') . '.webp';
        if (! imagewebp($thumb, $tmpThumb, $thumbQuality)) {
            imagedestroy($thumb);
            throw new \RuntimeException('Konversi WebP (thumb) gagal.');
        }
        imagedestroy($thumb);

        $pathThumb = $dirThumb . '/' . $filename;
        Storage::disk($disk)->makeDirectory($dirThumb);
        Storage::disk($disk)->put($pathThumb, file_get_contents($tmpThumb), 'public');
        @unlink($tmpThumb);

        return [
            'large' => $pathLarge,
            'thumb' => $pathThumb,
        ];
    }
}

/**
 * Simpan banyak file sebagai pasangan large & thumb.
 * return: array of ['large' => '...', 'thumb' => '...']
 */
if (! function_exists('storeManyAsWebpWithThumb')) {
    function storeManyAsWebpWithThumb(
        array $files,
        string $directory,
        string $disk = 'public',
        int $largeQuality = 82,
        int $thumbQuality = 75,
        int $thumbWidth = 300
    ): array {
        $saved = [];
        foreach ($files as $file) {
            /** @var TemporaryUploadedFile $file */
            $saved[] = storeAsWebpWithThumb(
                $file,
                $directory,
                $disk,
                $largeQuality,
                $thumbQuality,
                $thumbWidth
            );
        }
        return $saved;
    }
}

/**
 * Cek apakah string adalah URL absolut (http/https/data).
 */
if (! function_exists('is_absolute_image_url')) {
    function is_absolute_image_url(?string $path): bool
    {
        if (!is_string($path) || $path === '') return false;
        $t = ltrim($path, '/');
        return Str::startsWith($t, ['http://', 'https://', 'data:']);
    }
}

/**
 * Normalisasi path gambar menjadi URL publik yang valid.
 * - http/https/data: pakai langsung
 * - "storage/..."    : asset($path)
 * - selain itu       : asset("storage/$path")
 * - kosong/null      : kembalikan SVG data URI agar tidak 404
 */
if (! function_exists('image_url')) {
    function image_url(?string $path, ?string $fallback = null): string
    {
        // Jika ada path → normalisasi
        if (is_string($path) && $path !== '') {
            if (is_absolute_image_url($path)) {
                return $path;
            }
            $t = ltrim($path, '/');
            return Str::startsWith($t, 'storage/')
                ? asset($t)
                : asset('storage/' . $t);
        }

        // Jika kosong → fallback file/path jika diberikan
        if (is_string($fallback) && $fallback !== '') {
            return image_url($fallback, null);
        }

        // Fallback terakhir: SVG inline (tidak 404)
        return 'data:image/svg+xml;utf8,' . rawurlencode('
            <svg xmlns="http://www.w3.org/2000/svg" width="640" height="400">
              <rect width="100%" height="100%" fill="#f1f5f9"/>
              <text x="50%" y="50%" dominant-baseline="middle" text-anchor="middle"
                    font-family="sans-serif" font-size="18" fill="#94a3b8">No image</text>
            </svg>
        ');
    }
}

/**
 * URL gambar product dari model/array/path.
 * - Terima: App\Models\Product | array | string|null
 * - Prioritas: main_image
 */
if (! function_exists('product_image_url')) {
    function product_image_url($product, ?string $fallback = null): string
    {
        if (is_object($product)) {
            $path = $product->main_image ?? null;
        } elseif (is_array($product)) {
            $path = $product['main_image'] ?? null;
        } else {
            // langsung dianggap path
            $path = $product;
        }
        return image_url($path, $fallback);
    }
}

/**
 * URL gambar variant (dengan fallback ke gambar product jika disediakan).
 * - Terima: App\Models\ProductVariant | array | string|null
 */
if (! function_exists('variant_image_url')) {
    function variant_image_url($variant, $productMainImage = null, ?string $fallback = null): string
    {
        if (is_object($variant)) {
            $path = $variant->image ?? null;
        } elseif (is_array($variant)) {
            $path = $variant['image'] ?? null;
        } else {
            // langsung dianggap path
            $path = $variant;
        }

        // Jika variant kosong → pakai gambar produk (kalau ada)
        if (!is_string($path) || $path === '') {
            if ($productMainImage) {
                return image_url(is_object($productMainImage) ? ($productMainImage->main_image ?? null) : $productMainImage, $fallback);
            }
        }
        return image_url($path, $fallback);
    }
}

/**
 * URL gambar dari app settings (helper setting('key')).
 * - Contoh key: 'app_logo', 'app_image', dll.
 * - Bisa juga diberi fallback path (mis. 'images/logo.svg')
 */
if (! function_exists('setting_image_url')) {
    function setting_image_url(string $key, ?string $fallback = null): string
    {
        // kamu sudah punya helper setting()
        $raw = function_exists('setting') ? setting($key) : null;
        if (is_array($raw)) {
            $raw = $raw['value'] ?? $raw['path'] ?? $raw['url'] ?? null;
        }
        return image_url($raw, $fallback);
    }
}


if (! function_exists(function: 'brand_logo_url')) {
    function brand_logo_url(): string
    {
        // Ambil dari setting/appSettings/ENV – sesuaikan dengan milikmu
        $raw = function_exists('setting') ? setting('app_logo') : (function_exists('appSettings') ? appSettings('app_logo') : null);

        // Kalau array dari DB/Filament, ambil kuncinya yang masuk akal
        if (is_array($raw)) {
            $raw = $raw['path'] ?? $raw['url'] ?? $raw['value'] ?? (count($raw) ? reset($raw) : null);
        }

        // Sudah berupa URL absolut?
        if (is_string($raw) && preg_match('#^(https?:)?//#', $raw)) {
            return $raw;
        }

        // Path relatif (mis. "logos/app.png" atau "storage/logos/app.png")
        if (is_string($raw) && $raw !== '') {
            $p = ltrim($raw, '/');
            return str_starts_with($p, 'storage/')
                ? asset($p)
                : asset('storage/' . $p);
        }

        // Fallback placeholder (SVG data-uri, anti 404)
        return 'data:image/svg+xml;utf8,' . rawurlencode('<svg xmlns="http://www.w3.org/2000/svg" width="64" height="64"><rect width="100%" height="100%" rx="12" fill="#e5e7eb"/><text x="50%" y="54%" text-anchor="middle" font-size="18" font-family="sans-serif" fill="#6b7280">LOGO</text></svg>');
    }
}
