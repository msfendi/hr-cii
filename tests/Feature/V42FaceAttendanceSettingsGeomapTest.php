<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Http\Controllers\FaceAttendanceSettingController;

/**
 * V42 — Tiga perubahan pada halaman Pengaturan Presensi Wajah
 *        (resources/views/face_attendance/settings.blade.php).
 *
 * Permintaan user (2026-11-10):
 *
 *  1. "toggle input NPK dipindah dari card Liveness Check ke card
 *      Window Jam Presensi & Hari Kerja"
 *      -> Toggle hanya boleh ADA di card Window. Form liveness TIDAK
 *         lagi mengirim field is_npk_required_enabled, supaya tidak ada
 *         dua sumber pengaturan yang bisa saling menimpa.
 *
 *  2. "tampilkan minimap + polygon di card Geomap Transaksi Kantin"
 *      -> Card Geomap harus punya mini map Leaflet yang menggambar
 *         polygon area kantin, memakai helper normalisasi GeoJSON yang
 *         SAMA dengan halaman transaksi (yang digambar = yang dicek).
 *
 *  3. "UI/UX card Geomap Transaksi Kantin lebih interaktif"
 *      -> Pilih lokasi jadi checklist yang bisa diklik, ada badge jumlah
 *         terpilih, preview polygon yang berubah saat dicentang, tombol
 *         fit area, dan peringatan fail-closed untuk lokasi tanpa polygon.
 *
 * Test ini KONTRAK (contract test): memeriksa isi file view + controller.
 * Tidak menyentuh database remote.
 */
class V42FaceAttendanceSettingsGeomapTest extends TestCase
{
    private function source(string $relative): string
    {
        $base = realpath(__DIR__ . '/../../');
        $path = $base . '/' . $relative;
        $this->assertFileExists($path, "File tidak ditemukan: {$relative}");
        return (string) file_get_contents($path);
    }

    private function viewSource(): string
    {
        return $this->source('resources/views/face_attendance/settings.blade.php');
    }

    private function controller(): string
    {
        return $this->source('app/Http/Controllers/FaceAttendanceSettingController.php');
    }

    /**
     * Ambil isi form berdasarkan nama action route-nya.
     */
    private function formBlock(string $src, string $routeFragment): string
    {
        $pos = strpos($src, $routeFragment);
        $this->assertNotFalse($pos, "Form dengan route {$routeFragment} tidak ditemukan.");

        $start = strrpos(substr($src, 0, $pos), '<form');
        $this->assertNotFalse($start, "Tag <form> tidak ditemukan untuk {$routeFragment}.");

        $end = strpos($src, '</form>', $pos);
        $this->assertNotFalse($end, "Tag </form> tidak ditemukan untuk {$routeFragment}.");

        return substr($src, $start, $end - $start);
    }

    // ============================================================
    // BAGIAN 1 — TOGGLE INPUT NPK PINDAH KE CARD WINDOW
    // ============================================================

    public function test_toggle_npk_hanya_ada_di_form_window_bukan_form_liveness()
    {
        $src = $this->viewSource();

        $formWindow  = $this->formBlock($src, "face.attendance.settings.update-window");
        $formLiveness = $this->formBlock($src, "face.attendance.settings.update-liveness");

        // 1. Toggle WAJIB ada di form window.
        $this->assertStringContainsString(
            'name="is_npk_required_enabled"',
            $formWindow,
            'Toggle Input NPK Dulu WAJIB ada di form Window Jam Presensi'
        );

        // 2. Toggle TIDAK BOLEH ada di form liveness (sumber tunggal).
        $this->assertStringNotContainsString(
            'name="is_npk_required_enabled"',
            $formLiveness,
            'Form Liveness TIDAK BOLEH lagi mengirim is_npk_required_enabled '
            . '(dua sumber pengaturan akan saling menimpa)'
        );
    }

    public function test_form_window_mengirim_hidden_value_nol_sehingga_toggle_bisa_dimatikan()
    {
        $formWindow = $this->formBlock($this->viewSource(), "face.attendance.settings.update-window");

        $this->assertStringContainsString(
            '<input type="hidden" name="is_npk_required_enabled" value="0">',
            $formWindow,
            'Form window perlu hidden value=0 agar checkbox yang tidak dicentang '
            . 'tetap terkirim sebagai 0 (bukan hilang dari request)'
        );
    }

    public function test_form_window_menampilkan_nilai_toggle_sesuai_pengaturan_tersimpan()
    {
        $formWindow = $this->formBlock($this->viewSource(), "face.attendance.settings.update-window");

        $this->assertStringContainsString(
            "\$liveness->is_npk_required_enabled ?? true",
            $formWindow,
            'Checkbox harus mengambil nilai dari setting yang tersimpan '
            . '(bukan default hardcode), dengan fallback true'
        );
    }

    public function test_controller_update_window_menyimpan_toggle_npk()
    {
        $src = $this->controller();

        // updateWindow() harus menulis is_npk_required_enabled.
        $this->assertMatchesRegularExpression(
            '/public function updateWindow\(Request \$request\)[\s\S]{0,4000}?is_npk_required_enabled/',
            $src,
            'updateWindow() harus menyimpan is_npk_required_enabled'
        );
    }

    public function test_update_window_hanya_menulis_npk_kalau_field_itu_dikirim()
    {
        $src = $this->controller();

        $pos = strpos($src, 'public function updateWindow');
        $this->assertNotFalse($pos);

        $seg = substr($src, $pos, 4000);

        $this->assertStringContainsString(
            "\$request->has('is_npk_required_enabled')",
            $seg,
            'updateWindow() WAJIB mengecek $request->has() supaya tidak menimpa '
            . 'nilai lama bila field tidak dikirim'
        );
    }

    public function test_update_liveness_tidak_menimpa_npk_yang_tidak_dikirim()
    {
        $src = $this->controller();

        $pos = strpos($src, 'public function updateLiveness');
        $this->assertNotFalse($pos);

        // Ambil blok method sampai method berikutnya.
        $next = strpos($src, 'public function ', $pos + 20);
        $seg = substr($src, $pos, ($next === false ? strlen($src) : $next) - $pos);

        $this->assertStringContainsString(
            "\$request->has('is_npk_required_enabled')",
            $seg,
            'updateLiveness() WAJIB mengecek $request->has() -- kalau tidak, '
            . '$request->boolean() mengembalikan false dan nilai NPK yang sudah '
            . 'tersimpan akan tertimpa diam-diam setiap simpan pengaturan liveness'
        );
    }

    public function test_tombol_simpan_liveness_tidak_lagi_menyebut_npk()
    {
        $formLiveness = $this->formBlock($this->viewSource(), "face.attendance.settings.update-liveness");

        $this->assertStringNotContainsString(
            'Simpan Pengaturan Liveness & NPK',
            $formLiveness,
            'Label tombol harus diubah karena NPK tidak lagi disetting di kartu ini'
        );
    }

    // ============================================================
    // BAGIAN 2 — MINI MAP + POLYGON DI CARD GEOMAP
    // ============================================================

    public function test_card_geomap_mempunyai_kontainer_mini_map()
    {
        $src = $this->viewSource();

        $this->assertStringContainsString(
            'id="canteenGeomapMiniMap"',
            $src,
            'Card Geomap Transaksi Kantin harus punya elemen mini map'
        );
    }

    public function test_card_geomap_menghapus_select_multiple_dan_mengganti_checklist()
    {
        $formGeomap = $this->formBlock($this->viewSource(), "face.attendance.settings.update-canteen-geomap");

        $this->assertStringNotContainsString(
            'select name="allowed_work_location_ids[]"',
            $formGeomap,
            'Select multiple (harus pakai Ctrl/Cmd) diganti checklist yang bisa diklik'
        );

        $this->assertStringContainsString(
            'name="allowed_work_location_ids[]"',
            $formGeomap,
            'Checklist lokasi harus tetap memakai name allowed_work_location_ids[]'
        );
    }

    public function test_mini_map_menggunakan_helper_normalisasi_geojson_yang_sama_dengan_halaman_transaksi()
    {
        $src = $this->viewSource();

        $this->assertStringContainsString(
            'function extractPolygonRing(polygonGeojson)',
            $src,
            'Card Geomap harus punya helper extractPolygonRing()'
        );

        // Helper di halaman transaksi HARUS ada dengan nama yang sama.
        $transaksi = $this->source('resources/views/canteen_transaction/index.blade.php');
        $this->assertStringContainsString(
            'function extractPolygonRing(polygonGeojson)',
            $transaksi,
            'Halaman transaksi harus punya helper extractPolygonRing()'
        );
    }

    public function test_mini_map_gambar_polygon_dan_fit_bounds()
    {
        $src = $this->viewSource();

        $this->assertStringContainsString(
            'L.polygon(ring',
            $src,
            'Mini map harus menggambar polygon area'
        );

        $this->assertStringContainsString(
            'fitBounds',
            $src,
            'Mini map harus fit ke bounds polygon agar area pasti terlihat'
        );
    }

    public function test_mini_map_fail_closed_untuk_polygon_tidak_valid()
    {
        $src = $this->viewSource();

        $this->assertMatchesRegularExpression(
            '/ring\.length < 3/',
            $src,
            'Mini map harus menolak ring dengan < 3 titik (fail-closed)'
        );
    }

    public function test_data_polygon_dikirim_ke_frontend_dalam_bentuk_ring_ternormalisasi()
    {
        $src = $this->viewSource();

        $this->assertStringContainsString(
            "'ring'  => \$loc->coordinates ?? []",
            $src,
            'Backend harus mengirim ring ternormalisasi ke frontend '
            . '(meng pakai accessor coordinates yang menangani semua bentuk GeoJSON)'
        );

        $this->assertStringContainsString(
            'id="canteenGeomapLocationsJson"',
            $src,
            'Data lokasi harus dikirim ke frontend lewat hidden input JSON'
        );
    }

    public function test_leaflet_dimuat_di_halaman_settings()
    {
        $src = $this->viewSource();

        $this->assertStringContainsString(
            'leaflet@1.9.4/dist/leaflet.js',
            $src,
            'Leaflet JS harus dimuat di halaman settings'
        );

        $this->assertStringContainsString(
            'leaflet@1.9.4/dist/leaflet.css',
            $src,
            'Leaflet CSS harus dimuat di halaman settings'
        );
    }

    // ============================================================
    // BAGIAN 3 — UI/UX INTERAKTIF
    // ============================================================

    public function test_card_geomap_punya_badge_jumlah_terpilih()
    {
        $src = $this->viewSource();

        $this->assertStringContainsString(
            'id="canteenGeomapCountBadge"',
            $src,
            'Card Geomap harus punya badge jumlah lokasi terpilih'
        );
    }

    public function test_card_geomap_punya_tombol_fit_area()
    {
        $src = $this->viewSource();

        $this->assertStringContainsString(
            'id="canteenGeomapFitBtn"',
            $src,
            'Card Geomap harus punya tombol Fit Area'
        );
    }

    public function test_centang_lokasi_menyorot_polygon_di_peta()
    {
        $src = $this->viewSource();

        $this->assertStringContainsString(
            "addEventListener('change'",
            $src,
            'Perubahan checkbox harus memicu update peta'
        );

        $this->assertStringContainsString(
            'refreshStyles',
            $src,
            'Perubahan checkbox harus menyegarkan style polygon'
        );

        $this->assertStringContainsString(
            '#22c55e',
            $src,
            'Lokasi terpilih harus disorot dengan warna hijau'
        );
    }

    public function test_lokasi_tanpa_polygon_ditandai_peringatan()
    {
        $src = $this->viewSource();

        $this->assertStringContainsString(
            'data-loc-has-poly',
            $src,
            'Setiap lokasi harus menandai apakah punya polygon valid'
        );

        $this->assertStringContainsString(
            'fail-closed',
            $src,
            'View harus menjelaskan bahwa lokasi tanpa polygon ditolak (fail-closed)'
        );
    }

    public function test_klik_lokasi_memfokuskan_peta_ke_area_tersebut()
    {
        $src = $this->viewSource();

        $this->assertStringContainsString(
            ".canteen-loc-item",
            $src,
            'Baris lokasi harus punya kelas untuk event handler klik'
        );

        $this->assertStringContainsString(
            'poly.openPopup()',
            $src,
            'Klik lokasi harus membuka popup polygon'
        );
    }

    public function test_legenda_peta_ada_di_card_geomap()
    {
        $src = $this->viewSource();

        $this->assertStringContainsString(
            'Area GPS diizinkan',
            $src,
            'Peta harus punya legenda'
        );
    }

    public function test_ringkasan_konfirmasi_membaca_checklist_bukan_select()
    {
        $src = $this->viewSource();

        // Fungsi summarize() (:modal konfirmasi SweetAlert) WAJIB membaca
        // checkbox. Kalau masih membaca <select> yang sudah dihapus,
        // jumlah selalu 0 dan ringkasan konfirmasi selalu salah.
        $pos = strpos($src, 'function summarize()');
        $this->assertNotFalse($pos, 'Fungsi summarize() tidak ditemukan');

        $seg = substr($src, $pos, 1200);

        $this->assertStringContainsString(
            "querySelectorAll('input[name=\"allowed_work_location_ids[]\"]')",
            $seg,
            'summarize() harus membaca checkbox lokasi'
        );

        $this->assertStringNotContainsString(
            "querySelector('select[name=\"allowed_work_location_ids[]\"]')",
            $seg,
            'summarize() tidak boleh lagi membaca <select> yang sudah dihapus '
            . '(jumlah selalu 0 -> konfirmasi SweetAlert selalu salah)'
        );
    }

    public function test_form_geomap_tidak_lagi_memakai_select_multiple()
    {
        $formGeomap = $this->formBlock($this->viewSource(), "face.attendance.settings.update-canteen-geomap");

        $this->assertStringNotContainsString(
            '<select name="allowed_work_location_ids[]"',
            $formGeomap,
            'Select multiple sudah diganti checklist'
        );
    }

    public function test_checklist_lokasi_memakai_data_loc_id_yang_konsisten_dengan_js()
    {
        $src = $this->viewSource();

        $this->assertStringContainsString(
            'data-loc-id="{{ $loc->id }}"',
            $src,
            'Checkbox lokasi harus punya data-loc-id supaya JS bisa mencocokkan polygon'
        );

        $this->assertStringContainsString(
            '[data-loc-id="\' + locId + \'"]',
            $src,
            'JS harus mencari checkbox lewat data-loc-id'
        );
    }

    public function test_tidak_ada_karakter_cjk_di_file_yang_diubah()
    {
        $files = [
            'resources/views/face_attendance/settings.blade.php',
            'app/Http/Controllers/FaceAttendanceSettingController.php',
            'tests/Feature/V42FaceAttendanceSettingsGeomapTest.php',
        ];

        foreach ($files as $f) {
            $src = $this->source($f);

            $this->assertSame(
                0,
                preg_match('/[\x{3000}-\x{9fff}\x{3040}-\x{30ff}]/u', $src),
                "File {$f} mengandung karakter CJK"
            );
        }
    }
}
