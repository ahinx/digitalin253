@php
$manifestPath = public_path('build/manifest.json');
if (! file_exists($manifestPath)) {
throw new \RuntimeException('Vite manifest not found. Run: npm run build');
}
$manifest = json_decode(file_get_contents($manifestPath), true, 512, JSON_THROW_ON_ERROR);

// Map input entries ke output file
$cssEntry = $manifest['resources/css/app.css']['file'] ?? null;
$jsEntry = $manifest['resources/js/app.js']['file'] ?? null;

// Ambil CSS tambahan (chunks) jika ada
$cssImports = $manifest['resources/js/app.js']['css'] ?? [];
@endphp

@if($cssEntry)
<link rel="stylesheet" href="{{ asset('build/'.$cssEntry) }}">
@endif

@foreach($cssImports as $css)
<link rel="stylesheet" href="{{ asset('build/'.$css) }}">
@endforeach

@if($jsEntry)
<script type="module" src="{{ asset('build/'.$jsEntry) }}"></script>
@endif