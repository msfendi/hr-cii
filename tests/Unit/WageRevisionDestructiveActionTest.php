<?php

namespace Tests\Unit;

use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\TestCase;

/**
 * Aksi merusak (setujui/hapus) pada modul Penyesuaian Upah.
 *
 * Yang dijaga:
 *  - hapus batch & hapus item memakai method DELETE (POST + spoof), bukan GET.
 *    Dengan GET, JS konfirmasi yang gagal dimuat akan langsung menghapus data;
 *    dengan DELETE, kegagalan itu berakhir 405 sehingga data tetap utuh.
 *  - Setujui Batch punya dialog konfirmasi DAN dialog loading setelahnya.
 */
class WageRevisionDestructiveActionTest extends TestCase
{
    private string $blade;

    protected function setUp(): void
    {
        parent::setUp();

        $container = \Illuminate\Container\Container::getInstance();

        if ($container === null || ! $container->bound('router')) {
            $app = require dirname(__DIR__, 2) . '/bootstrap/app.php';
            $app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
        }

        $this->blade = (string) file_get_contents(dirname(__DIR__, 2) . '/resources/views/wage_revisions/show.blade.php');
    }

    public function test_hapus_batch_dan_item_wajib_method_delete(): void
    {
        $rute = [];

        foreach (Route::getRoutes()->getRoutes() as $route) {
            if ($route->getName()) {
                $rute[$route->getName()] = $route;
            }
        }

        foreach (['wage-revisions.destroy', 'wage-revisions.item.delete'] as $nama) {
            $this->assertArrayHasKey($nama, $rute, "route {$nama} tidak terdaftar");

            $methods = $rute[$nama]->methods();
            $this->assertContains('DELETE', $methods, "{$nama} harus DELETE");
            $this->assertNotContains('GET', $methods, "{$nama} tidak boleh bisa diakses lewat GET");
            $this->assertNotContains('POST', $methods, "{$nama} harus DELETE saja (form memakai spoof _method)");
        }
    }

    public function test_form_hapus_memakai_post_csrf_dan_spoof_delete(): void
    {
        $this->assertStringNotContainsString(
            'method="GET" action="{{ route(\'wage-revisions.destroy\'',
            $this->blade,
            'form hapus batch tidak boleh GET'
        );
        $this->assertStringNotContainsString(
            'method="GET" action="{{ route(\'wage-revisions.item.delete\'',
            $this->blade,
            'form hapus item tidak boleh GET'
        );

        $this->assertSame(2, substr_count($this->blade, "@method('DELETE')"), 'dua form hapus wajib spoof DELETE');
        $this->assertSame(
            1,
            substr_count($this->blade, 'method="POST" action="{{ route(\'wage-revisions.destroy\''),
            'form hapus batch wajib POST'
        );
        $this->assertSame(
            1,
            substr_count($this->blade, 'method="POST" action="{{ route(\'wage-revisions.item.delete\''),
            'form hapus item wajib POST'
        );
    }

    public function test_setujui_batch_punya_konfirmasi_dan_loading(): void
    {
        $this->assertMatchesRegularExpression(
            '/<form method="POST" action="\{\{ route\(\'wage-revisions\.approve\'.*?<\/form>/s',
            $this->blade,
            'form Setujui Batch tidak ditemukan'
        );

        preg_match('/<form method="POST" action="\{\{ route\(\'wage-revisions\.approve\'.*?<\/form>/s', $this->blade, $m);
        $form = $m[0];

        $this->assertStringContainsString('js-confirm-submit', $form, 'Setujui Batch wajib lewat handler konfirmasi');
        $this->assertStringContainsString('data-confirm-title="Setujui batch ini?"', $form);
        $this->assertStringContainsString('data-confirm-button="Ya, setujui batch"', $form);
        $this->assertStringContainsString('data-confirm-loading="Menyetujui batch..."', $form, 'wajib ada dialog loading');
        $this->assertStringContainsString('data-confirm-loading-text=', $form);
        $this->assertStringContainsString('@csrf', $form, 'form POST wajib CSRF');
    }

    public function test_semua_form_hapus_punya_teks_loading(): void
    {
        preg_match_all('/<form[^>]*action="\{\{ route\(\'wage-revisions\.(destroy|item\.delete)\'.*?<\/form>/s', $this->blade, $m);

        $this->assertCount(2, $m[0], 'harus ada dua form hapus (batch dan item)');

        foreach ($m[0] as $form) {
            $this->assertStringContainsString('js-confirm-submit', $form);
            $this->assertStringContainsString('data-confirm-title=', $form);
            $this->assertStringContainsString('data-confirm-loading=', $form);
            $this->assertStringContainsString('@method(\'DELETE\')', $form);
        }
    }
}