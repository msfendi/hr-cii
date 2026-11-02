import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';

export default defineConfig({
    plugins: [
        laravel({
            // Hanya JS yang di-build lewat Vite. Stylesheet aplikasi memakai
            // public/css/*.css (sb-admin-2) yang di-link langsung di layout.
            // Jika app.css (kosong) ikut jadi entry, Vite menghasilkan stub .js
            // dan manifest rusak -> browser gagal load stylesheet.
            input: ['resources/js/app.js'],
            refresh: true,
        }),
    ],
    server: {
        // Dengarkan di SEMUA interface (IPv4 + IPv6) supaya dev server bisa
        // diakses lewat domain apa pun yang diarahkan ke host ini
        // (localhost, *.test, IP LAN, hostname mesin, domain production).
        // 0.0.0.0 = bind wildcard, bukan URL yang diklaws browser.
        host: '0.0.0.0',
        port: 5173,
        strictPort: true,
        // Vite >= 5 memblokir request dengan Host header yang tidak dikenal
        // (DNS rebinding protection). Tanpa ini dev server membalas
        // "403 Forbidden" untuk /@vite/client dan /resources/js/app.js saat
        // diakses lewat IP LAN / domain internal.
        allowedHosts: true,
        // Izinkan SEMUA origin aplikasi. Browser hanya menampilkan error CORS
        // bila server tidak mengirim header Access-Control-Allow-Origin.
        // Regex "null" tetap diizinkan agar halaman dari file:// dev tetap jalan.
        cors: {
            origin: true,
            methods: ['GET', 'HEAD', 'PUT', 'POST', 'DELETE', 'PATCH', 'OPTIONS'],
            headers: ['*'],
        },
        // HMR client harus connect ke host yang memang bisa dijangkau browser.
        // Jangan set `host` di sini: nilai literal akan ditulis apa adanya ke
        // public/hot (mis. "http://true:5173") dan HMR gagal connect.
        // Tanpa `host`, Vite memakai Host header dari request, sehingga
        // otomatis mengikuti host aktif (localhost / *.test / IP LAN /
        // domain production) tanpa ada domain atau IP yang dikunci di config.
        // protocol + clientPort tetap ditulis eksplisit karena port dev
        // sudah pasti 5173 (strictPort di atas).
        hmr: {
            protocol: 'ws',
            clientPort: 5173,
        },
    },
});
