<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * PERBAIKAN V32 (2026-11-09) -- SUBMENU SIDEBAR YANG TIDAK MAU TERTUTUP.
 *
 * Bug yang dilaporkan user:
 *   "di /face-attendance/settings dan /face-attendance/admin/log kenapa
 *    setelah dropdown sidebar terbuka sub menunya ketika diklik lagi
 *    dropdown sub menu tidak mau tertutup?"
 *
 * Akar masalah:
 *   Handler "Search Menu" di resources/views/layout/navbar.blade.php
 *   memanipulasi class 'show' LANGSUNG:
 *       collapseEl.classList.toggle('show', showParent);
 *
 *   Bootstrap 4 menyimpan state buka/tutup di state internal plugin
 *   (bs.collapse), bukan di class 'show'. Manipulasi class langsung
 *   membuat DOM dan state plugin TIDAK sinkron.
 *
 *   Akibatnya setelah user search (submenu auto-terbuka via class
 *   'show'), klik parent berikutnya:
 *     - plugin mengira submenu masih tertutup
 *     - memanggil show() padahal class 'show' SUDAH ada
 *     - hasil: tidak terjadi apa-apa / klik kedua baru menutup
 *
 * Fix:
 *   Semua buka/tutup lewat API plugin Bootstrap
 *   (instance.show() / instance.hide()), plus menunggu transisi yang
 *   sedang berjalan supaya tidak ada state setengah jadi.
 */
class SidebarCollapseContractTest extends TestCase
{
    private function navbarSource(): string
    {
        return file_get_contents(base_path('resources/views/layout/navbar.blade.php'));
    }

    public function test_search_handler_does_not_toggle_show_class_directly()
    {
        $src = $this->navbarSource();

        // Pattern yang harmful: classList.toggle('show', ...) pada elemen collapse.
        // Catatan: 'show' juga dipakai untuk dropdown navbar (menu account),
        // jadi yang diperiksa adalah pemakaian pada konteks collapse.
        $this->assertDoesNotMatchRegularExpression(
            "/collapseEl\.classList\.toggle\(\s*'show'/",
            $src,
            'Handler Search Menu masih memanipulasi class show langsung pada collapse. '
            .'Gunakan setCollapseState() yang memanggil API plugin Bootstrap.'
        );
    }

    public function test_set_collapse_state_helper_exists()
    {
        $src = $this->navbarSource();

        $this->assertStringContainsString(
            'function setCollapseState(el, shouldBeOpen)',
            $src,
            'Helper setCollapseState() tidak ditemukan di navbar.blade.php.'
        );
    }

    public function test_set_collapse_state_uses_bootstrap_plugin_api()
    {
        $src = $this->navbarSource();

        // Helper harus memanggil instance.show() dan instance.hide().
        $this->assertStringContainsString('instance.show();', $src);
        $this->assertStringContainsString('instance.hide();', $src);

        // Dan harus mengambil instance dari plugin Bootstrap.
        // Bentuk yang dipakai: $.data(el, 'bs.collapse')
        $this->assertStringContainsString("'bs.collapse'", $src);
    }

    public function test_set_collapse_state_waits_for_running_transition()
    {
        $src = $this->navbarSource();

        $this->assertStringContainsString(
            'instance._isTransitioning',
            $src,
            'setCollapseState() harus mengecek _isTransitioning. Bootstrap MENGABAIKAN '
            .'show()/hide() saat transisi sedang berjalan, sehingga state yang '
            .'diminta hilang dan submenu tertinggal setengah terbuka.'
        );

        // Event transisi Bootstrap 4: shown.bs.collapse / hidden.bs.collapse.
        $this->assertStringContainsString('shown.bs.collapse', $src);
        $this->assertStringContainsString('hidden.bs.collapse', $src);
    }

    public function test_filter_sidebar_uses_set_collapse_state()
    {
        $src = $this->navbarSource();

        $this->assertStringContainsString(
            'setCollapseState(collapseEl, showParent)',
            $src,
            'filterSidebar() harus memakai setCollapseState() agar state plugin sinkron.'
        );
    }

    public function test_reset_sidebar_uses_set_collapse_state()
    {
        $src = $this->navbarSource();

        $this->assertStringContainsString(
            'setCollapseState(el, originallyOpen.has(el.id))',
            $src,
            'resetSidebar() harus memakai setCollapseState() agar state plugin sinkron.'
        );
    }

    public function test_sidebar_markup_uses_bootstrap_collapse_data_attributes()
    {
        $src = file_get_contents(base_path('resources/views/layout/sidebar.blade.php'));

        $this->assertStringContainsString('data-toggle="collapse"', $src);
        $this->assertStringContainsString('data-parent="#accordionSidebar"', $src);
        $this->assertStringContainsString('id="accordionSidebar"', $src);
    }

    public function test_no_cjk_characters_in_patched_files()
    {
        $files = [
            'resources/views/layout/navbar.blade.php',
            'resources/views/layout/sidebar.blade.php',
            'resources/views/face_attendance/settings.blade.php',
            'app/Http/Controllers/FaceAttendanceSettingController.php',
            'app/Http/Controllers/SatisfactionSurveyController.php',
            'app/Services/SurveySanitizer.php',
            'app/Exports/SatisfactionSurveyResultExport.php',
            'app/Exports/SatisfactionLikertDistributionExport.php',
            'resources/views/satisfaction_survey/admin_index.blade.php',
        ];

        foreach ($files as $rel) {
            $path = base_path($rel);
            $this->assertFileExists($path);

            $content = file_get_contents($path);
            $cjk = preg_match_all('/[\x{3000}-\x{9FFF}\x{FF00}-\x{FFEF}]/u', $content);

            $this->assertSame(
                0,
                $cjk,
                "File {$rel} mengandung karakter CJK. Komentar/kode harus bebas CJK."
            );
        }
    }
}
