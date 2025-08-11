import "./bootstrap";

import Alpine from "alpinejs";

// Jalankan AlpineJS
Alpine.start();

// Tombol search mobile sederhana
const searchBtn = document.getElementById("open-search");
if (searchBtn) {
    searchBtn.addEventListener("click", () => {
        const q = prompt("Cari produk…");
        if (q !== null) {
            const url = new URL(window.location.href);
            url.searchParams.set("q", q);
            window.location.href = url.toString();
        }
    });
}
