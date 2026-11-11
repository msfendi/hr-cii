<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * V43 — Tiga bug yang dilaporkan user (2026-11-10) setelah deploy V41/V42:
 *
 *  1. SPLASH HIJAU /canteen-transaction menutupi seluruh kamera.
 *     Overlay rgba(28,200,138,.92) full-cover -> wajah tidak terlihat.
 *     Fix: pola Face Attendance — kamera tetap terlihat, hijau jadi
 *     tint tipis + border + badge pill + lingkaran scanner hijau +
 *     teks di bawah panel.
 *
 *  2. PETA AREA TRANSAKSI KANTIN di /face-attendance/settings "polygon
 *     rusak": L.polygon(ring [LNG,LAT]) -> getBounds() lat/lng TERBALIK
 *     -> fitBounds() mengarah ke lat ~85 (clamp Web Mercator) -> peta
 *     jadi blok abu-abu tanpa tile, zoom 18-19. Fix: pakai L.geoJSON()
 *     (pola /face-attendance/map) yang menafsirkan GeoJSON [LNG,LAT]
 *     dengan benar.
 *
 *  3. UX "Geomap yang diperbolehkan" tidak jelas mana yang sudah dipilih.
 *     Fix: badge "Dipilih" + baris hijau (loc-checked) yang sinkron
 *     dengan state checkbox via CSS + toggle class saat change.
 *
 *  4. Bug yang sama (L.polygon + fitBounds) juga ada di minimap
 *     /canteen-transaction — sudah diperbaiki dengan pola yang sama.
 *
 * Test ini KONTRAK (contract test): memeriksa isi file view.
 * Tidak menyentuh database remote.
 */
class V43CanteenMapAndSplashFixTest extends TestCase
{
    private function source(string $relative): string
    {
        $base = realpath(__DIR__ . '/../../');
        $path = $base . '/' . $relative;
        $this->assertFileExists($path, "File tidak ditemukan: {$relative}");
        return (string) file_get_contents($path);
    }

    private function canteenView(): string
    {
        return $this->source('resources/views/canteen_transaction/index.blade.php');
    }

    private function settingsView(): string
    {
        return $this->source('resources/views/face_attendance/settings.blade.php');
    }

    // ============================================================
    // BAGIAN 1 — SPLASH HIJAU TIDAK LAGI MENUTUPI KAMERA
    // ============================================================

    public function test_splash_hijau_bukan_blok_opak_penuh()
    {
        $src = $this->canteenView();

        // Overlay lama yang opak (0.92) harus sudah tidak ada.
        $this->assertStringNotContainsString(
            'background:rgba(28,200,138,.92)',
            $src,
            'Overlay hijau masih opak penuh (0.92) — kamera tertutup.'
        );

        // Overlay baru: tint tipis + border hijau.
        $this->assertStringContainsString(
            'background:rgba(28,200,138,.16)',
            $src,
            'Overlay baru harus memakai tint tipis (0.16), bukan opak.'
        );
        $this->assertStringContainsString(
            'box-shadow:inset 0 0 0 4px rgba(28,200,138,.95)',
            $src,
            'Overlay baru harus memakai border hijau (inset box-shadow).'
        );
    }

    public function test_splash_hijau_punya_badge_pill_dan_teks_di_bawah_panel()
    {
        $src = $this->canteenView();

        // Badge pill hijau (mengikuti camOverlayBadge di Face Attendance).
        $this->assertStringContainsString('id="faceVerifiedBadge"', $src);
        $this->assertStringContainsString('Wajah Terverifikasi', $src);

        // Teks status di bawah panel kamera, bukan di dalam overlay.
        $this->assertStringContainsString('id="faceVerifiedMsg"', $src);
        $this->assertStringContainsString('id="faceVerifiedFlashMsg"', $src);
    }

    public function test_show_flash_mengaktifkan_badge_dan_lingkaran_hijau()
    {
        $src = $this->canteenView();

        // showFaceVerifiedFlash harus menyalakan badge + guide hijau.
        $this->assertMatchesRegularExpression(
            '/function showFaceVerifiedFlash.*?faceVerifiedBadge/s',
            $src,
            'showFaceVerifiedFlash harus mengakses faceVerifiedBadge.'
        );
        $this->assertMatchesRegularExpression(
            '/function showFaceVerifiedFlash.*?scannerGuide.*?liveness-ok/s',
            $src,
            'showFaceVerifiedFlash harus menambah kelas liveness-ok ke scannerGuide.'
        );

        // hideFaceVerifiedFlash harus mematikan semuanya.
        $this->assertMatchesRegularExpression(
            '/function hideFaceVerifiedFlash.*?scannerGuide.*?remove\(.liveness-ok.\)/s',
            $src,
            'hideFaceVerifiedFlash harus menghapus kelas liveness-ok.'
        );
    }

    public function test_splash_hanya_muncul_setelah_response_server_sukses()
    {
        $src = $this->canteenView();

        // Panggilan showFaceVerifiedFlash harus ada di cabang res.ok && data.success.
        $this->assertMatchesRegularExpression(
            '/res\.ok\s*&&\s*data\.success[\s\S]{0,400}?showFaceVerifiedFlash/s',
            $src,
            'showFaceVerifiedFlash harus dipanggil setelah res.ok && data.success, bukan saat challenge lolos.'
        );
    }

    // ============================================================
    // BAGIAN 2 — POLYGON RUSAK: L.polygon -> L.geoJSON
    // ============================================================

    public function test_settings_geomap_memakai_geoJSON_bukan_polygon_langsung()
    {
        $src = $this->settingsView();

        // Harus ada L.geoJSON untuk menggambar polygon.
        $this->assertStringContainsString(
            'window.L.geoJSON(geoJson',
            $src,
            'Peta Area Transaksi Kantin harus memakai L.geoJSON (bukan L.polygon dengan ring [LNG,LAT]).'
        );

        // Ring tetap dibungkus sebagai GeoJSON Polygon coordinates.
        $this->assertMatchesRegularExpression(
            '/geometry:\s*\{\s*type:\s*.Polygon.\s*,\s*coordinates:\s*\[ring\]/s',
            $src,
            'Ring harus dibungkus sebagai geometry.coordinates [ring] di dalam Feature.'
        );

        // L.polygon dengan ring langsung tidak boleh lagi dipakai untuk menggambar.
        // (Cek pada kode aktif saja — komentar dokumentasi boleh menyebutnya.)
        $codeOnly = preg_replace('#//.*$#m', '', $src);
        $codeOnly = preg_replace('#/\*.*?\*/#s', '', $codeOnly);
        $this->assertStringNotContainsString(
            'window.L.polygon(ring',
            $codeOnly,
            'L.polygon(ring [LNG,LAT]) adalah akar bug polygon rusak — harus diganti L.geoJSON.'
        );
    }

    public function test_settings_geomap_attribution_ditampilkan()
    {
        $src = $this->settingsView();

        $this->assertStringContainsString(
            'attributionControl: true',
            $src,
            'attributionControl harus true supaya atribusi Leaflet/OSM terlihat (tanda peta hidup).'
        );
    }

    public function test_canteen_minimap_juga_memakai_geoJSON()
    {
        $src = $this->canteenView();

        $this->assertStringContainsString(
            'window.L.geoJSON(geoJson',
            $src,
            'Minimap /canteen-transaction juga harus memakai L.geoJSON.'
        );

        // L.polygon(ring) sebagai KODE (bukan komentar) tidak boleh ada.
        // Strip komentar JS/CSS dulu supaya yang dicek memang kode aktif.
        $codeOnly = preg_replace('#//.*$#m', '', $src);
        $codeOnly = preg_replace('#/\*.*?\*/#s', '', $codeOnly);
        $this->assertStringNotContainsString(
            'L.polygon(ring',
            $codeOnly,
            'L.polygon(ring [LNG,LAT]) di minimap canteen juga akar bug — harus diganti L.geoJSON.'
        );
    }

    public function test_point_in_polygon_tetap_memakai_konvensi_lng_lat()
    {
        $src = $this->canteenView();

        // pointInPolygon BUKAN Leaflet, jadi konvensi [lng,lat] tetap benar di sana.
        // Pastikan tidak ikut "diperbaiki" (yang justru akan merusak area check).
        $this->assertMatchesRegularExpression(
            '/function pointInPolygon\(lat,\s*lng,\s*polygon\)[\s\S]{0,300}?polygon\[i\]\[0\].*?p_lng_i/s',
            $src,
            'pointInPolygon harus tetap membaca polygon[i][0] sebagai lng (konvensi GeoJSON).'
        );
    }

    public function test_fit_bounds_memakai_polygon_yang_sudah_valid()
    {
        $src = $this->settingsView();

        // fitAll harus jalan setelah semua polygon digambar via geoJSON.
        $this->assertMatchesRegularExpression(
            '/allBounds[\s\S]{0,200}?fitAll\(\)/s',
            $src,
            'fitAll() harus dipanggil setelah allBounds diisi dari polygon.getBounds().'
        );
    }

    // ============================================================
    // BAGIAN 3 — UX CHECKLIST: TANDA "DIPILIH" JELAS
    // ============================================================

    public function test_checklist_punya_badge_dipilih()
    {
        $src = $this->settingsView();

        $this->assertStringContainsString('loc-selected-badge', $src);
        $this->assertStringContainsString('Dipilih', $src);
    }

    public function test_css_loc_checked_menampilkan_badge_dan_warna_baris()
    {
        $src = $this->settingsView();

        $this->assertMatchesRegularExpression(
            '/\.canteen-loc-item\.loc-checked\s*\{[^}]*background-color:\s*#eafaf2/s',
            $src,
            'Baris terpilih harus punya background hijau muda (#eafaf2).'
        );
        $this->assertMatchesRegularExpression(
            '/\.canteen-loc-item\.loc-checked \.loc-selected-badge\s*\{\s*display:\s*inline-flex/s',
            $src,
            'Badge "Dipilih" harus muncul (display:inline-flex) saat baris loc-checked.'
        );
        $this->assertMatchesRegularExpression(
            '/\.canteen-loc-item \.loc-selected-badge\s*\{\s*display:\s*none/s',
            $src,
            'Badge "Dipilih" harus tersembunyi (display:none) saat baris tidak terpilih.'
        );
    }

    public function test_perubahan_checkbox_memperbarui_kelas_loc_checked()
    {
        $src = $this->settingsView();

        $this->assertMatchesRegularExpression(
            "/change'[\s\S]{0,400}?classList\.toggle\('loc-checked',\s*cb\.checked\)/s",
            $src,
            'Saat checkbox berubah, kelas loc-checked pada baris harus di-toggle sesuai state checkbox.'
        );
    }

    public function test_badge_awal_checked_dari_server_memakai_loc_checked()
    {
        $src = $this->settingsView();

        // Blade harus mengeluarkan class loc-checked (bukan bg-light) saat $isChecked.
        $this->assertStringContainsString(
            "{{ \$isChecked ? 'loc-checked' : '' }}",
            $src,
            'Saat load, baris yang sudah terpilih harus memakai class loc-checked.'
        );
    }

    // ============================================================
    // BAGIAN 4 — BERSIH: tidak ada karakter CJK di view yang diubah
    // ============================================================

    public function test_tidak_ada_karakter_cjk_di_kedua_view()
    {
        foreach ([
            'resources/views/canteen_transaction/index.blade.php',
            'resources/views/face_attendance/settings.blade.php',
        ] as $rel) {
            $src = $this->source($rel);
            $this->assertDoesNotMatchRegularExpression(
                '/[\x{3000}-\x{9fff}\x{3040}-\x{30ff}]/u',
                $src,
                "Ditemukan karakter CJK di {$rel}."
            );
        }
    }
}
