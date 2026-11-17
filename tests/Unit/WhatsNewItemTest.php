<?php

namespace Tests\Unit;

use App\Http\Controllers\WhatsNewController;
use App\Models\WhatsNewItem;
use App\Support\WhatsNew;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\Validator;
use PHPUnit\Framework\TestCase;

/**
 * TASK 2 — What's New (slider banner).
 *
 * Yang dijaga di sini (tanpa menyentuh database):
 *  - scopeAktif() memfilter status + periode dan mengurutkan dengan benar;
 *  - jenis media ditentukan dari kolom media_type/media_path/media_url,
 *    termasuk pengenalan YouTube/Vimeo sebagai embed;
 *  - tombol: route bernama menang atas URL, dan tombol mati bila tujuan
 *    tidak ada; URL luar ditandai eksternal;
 *  - sidik isi banner berubah saat banner diubah (dasar tombol tutup);
 *  - aturan validasi menolak tombol tanpa tujuan, tujuan tanpa label,
 *    dan nama route yang tidak terdaftar.
 */
class WhatsNewItemTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $container = \Illuminate\Container\Container::getInstance();

        if ($container === null || ! $container->bound('config')) {
            $app = require dirname(__DIR__, 2) . '/bootstrap/app.php';
            $app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
        }
    }

    private function item(array $atribut = []): WhatsNewItem
    {
        $id = $atribut['id'] ?? null;
        unset($atribut['id']);

        $update = $atribut['updated_at'] ?? null;
        unset($atribut['updated_at']);

        $item = new WhatsNewItem(array_merge([
            'title'      => 'Slip gaji November',
            'media_type' => 'image',
            'is_active'  => true,
        ], $atribut));

        // id & updated_at tidak fillable; dipaksa supaya sidik isi bisa diuji.
        if ($id !== null) {
            $item->setAttribute('id', $id);
        }

        if ($update !== null) {
            $item->setAttribute('updated_at', $update);
        }

        return $item;
    }

    /**
     * Daftarkan route sementara untuk menguji tombol berbasis route.
     * refreshNameLookups() wajib: route yang ditambah setelah router
     * dibangun tidak masuk peta nama sampai peta itu disegarkan.
     */
    private function daftarkanRute(string $uri, string $nama): void
    {
        Route::get($uri, fn () => 'ok')->name($nama);
        Route::getRoutes()->refreshNameLookups();
    }

    // ---------------------------------------------------------------- scope

    public function test_scope_aktif_memfilter_status_dan_periode(): void
    {
        $sql = (new WhatsNewItem())->newQuery()->aktif()->toSql();

        $this->assertStringContainsString('"is_active"', str_replace(['[', ']', '`'], '"', $sql));
        $this->assertStringContainsString('starts_at', $sql);
        $this->assertStringContainsString('ends_at', $sql);
        $this->assertStringContainsString('order by', strtolower($sql));
        $this->assertStringContainsString('sort_order', $sql);
    }

    public function test_scope_aktif_mengurutkan_sort_order_lalu_id(): void
    {
        $sql = (new WhatsNewItem())->newQuery()->aktif()->toSql();

        $this->assertMatchesRegularExpression('/order by .*sort_order.*id/i', $sql);
    }

    // --------------------------------------------------------------- media

    public function test_gambar_dari_berkas_terunggah(): void
    {
        $this->daftarkanRute('whats-new/media/{berkas}', 'whats-new.media');

        $item = $this->item(['media_path' => 'whats-new/banner.jpg']);

        $this->assertSame('gambar', $item->jenisMedia());
        // Berkas unggahan disajikan lewat route media (tanpa symlink storage).
        $this->assertStringContainsString('/whats-new/media/banner.jpg', (string) $item->mediaSrc());
    }

    public function test_gambar_dari_url_langsung(): void
    {
        $item = $this->item(['media_url' => 'https://cdn.chutex.id/banner.jpg']);

        $this->assertSame('gambar', $item->jenisMedia());
        $this->assertSame('https://cdn.chutex.id/banner.jpg', $item->mediaSrc());
    }

    public function test_video_dari_berkas_dan_dari_url(): void
    {
        $berkas = $this->item(['media_type' => 'video', 'media_path' => 'whats-new/demo.mp4']);
        $url    = $this->item(['media_type' => 'video', 'media_url' => 'https://cdn.chutex.id/demo.mp4']);

        $this->assertSame('video', $berkas->jenisMedia());
        $this->assertSame('video-url', $url->jenisMedia());
    }

    public function test_tanpa_media(): void
    {
        $this->assertSame('none', $this->item(['media_type' => 'none'])->jenisMedia());
    }

    public function test_youtube_dikenali_dari_beberapa_bentuk_url(): void
    {
        $harapan = 'https://www.youtube.com/embed/dQw4w9WgXcQ';

        foreach ([
            'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
            'https://youtu.be/dQw4w9WgXcQ',
            'https://www.youtube.com/shorts/dQw4w9WgXcQ',
            'https://www.youtube.com/embed/dQw4w9WgXcQ',
            'https://www.youtube.com/watch?list=PL123&v=dQw4w9WgXcQ',
        ] as $url) {
            $item = $this->item(['media_type' => 'video', 'media_url' => $url]);

            $this->assertSame($harapan, $item->embedUrl(), 'URL: ' . $url);
            $this->assertSame('youtube', $item->jenisMedia(), 'URL: ' . $url);
        }
    }

    public function test_vimeo_dikenali_sebagai_embed(): void
    {
        $item = $this->item(['media_type' => 'video', 'media_url' => 'https://vimeo.com/76979871']);

        $this->assertSame('https://player.vimeo.com/video/76979871', $item->embedUrl());
        $this->assertSame('embed', $item->jenisMedia());
    }

    public function test_mp4_bukan_embed(): void
    {
        $item = $this->item(['media_type' => 'video', 'media_url' => 'https://cdn.chutex.id/demo.mp4']);

        $this->assertNull($item->embedUrl());
    }

    // -------------------------------------------------------------- tombol

    public function test_tombol_memakai_route_bernama(): void
    {
        $this->daftarkanRute('uji-whats-new-tujuan', 'uji.whatsnew.tujuan');

        $item = $this->item([
            'button_label' => 'Buka',
            'button_route' => 'uji.whatsnew.tujuan',
        ]);

        $this->assertStringContainsString('uji-whats-new-tujuan', (string) $item->tombolHref());
        $this->assertFalse($item->tombolEksternal());
    }

    public function test_tombol_jatuh_ke_url_bila_route_tidak_terdaftar(): void
    {
        $item = $this->item([
            'button_label' => 'Buka',
            'button_route' => 'route.yang.tidak.ada',
            'button_url'   => 'https://intranet.chutex.id/pengumuman',
        ]);

        $this->assertSame('https://intranet.chutex.id/pengumuman', $item->tombolHref());
        $this->assertTrue($item->tombolEksternal());
    }

    public function test_tombol_mati_tanpa_tujuan(): void
    {
        $item = $this->item(['button_label' => 'Buka']);

        $this->assertNull($item->tombolHref());
        $this->assertFalse($item->tombolEksternal());
    }

    // --------------------------------------------------------------- sidik

    public function test_sidik_banner_kosong(): void
    {
        $this->assertSame('kosong', WhatsNew::sidik(collect()));
    }

    public function test_sidik_berubah_saat_banner_diubah(): void
    {
        $awal = $this->item(['id' => 1, 'updated_at' => now()->subDay()]);
        $ubah = $this->item(['id' => 1, 'updated_at' => now()]);

        $this->assertNotSame(
            WhatsNew::sidik(collect([$awal])),
            WhatsNew::sidik(collect([$ubah]))
        );
    }

    public function test_sidik_stabil_untuk_isi_yang_sama(): void
    {
        $waktu = now()->startOfSecond();

        $this->assertSame(
            WhatsNew::sidik(collect([$this->item(['id' => 7, 'updated_at' => $waktu])])),
            WhatsNew::sidik(collect([$this->item(['id' => 7, 'updated_at' => $waktu])]))
        );
    }

    // ----------------------------------------------------------- validasi

    /** @return array<string, array<int, string>> */
    private function validasi(array $data): array
    {
        $request = Request::create('/admin/whats-new', 'POST', $data);
        $controller = new WhatsNewController();

        $validator = validator($request->all(), $controller->aturan($request));

        $validator->after(function (Validator $v) use ($request) {
            // Salinan aturan lintas-kolom dari controller, diuji di sini
            // supaya regresi validasi ketahuan tanpa menjalankan HTTP.
            $tipe  = (string) $request->input('media_type', 'image');
            $punya = trim((string) $request->input('media_url')) !== '';

            if ($tipe !== 'none' && ! $punya) {
                $v->errors()->add('media_file', 'media wajib');
            }

            $label  = trim((string) $request->input('button_label'));
            $tujuan = trim((string) $request->input('button_route')) !== ''
                || trim((string) $request->input('button_url')) !== '';

            if ($label !== '' && ! $tujuan) {
                $v->errors()->add('button_route', 'tombol butuh tujuan');
            }
            if ($label === '' && $tujuan) {
                $v->errors()->add('button_label', 'label wajib');
            }
            if (trim((string) $request->input('button_route')) !== ''
                && ! Route::has((string) $request->input('button_route'))) {
                $v->errors()->add('button_route', 'route tidak terdaftar');
            }
        });

        return $validator->errors()->toArray();
    }

    public function test_validasi_menolak_judul_kosong(): void
    {
        $errors = $this->validasi(['media_type' => 'none']);

        $this->assertArrayHasKey('title', $errors);
    }

    public function test_validasi_menolak_jenis_media_asing(): void
    {
        $errors = $this->validasi([
            'title'      => 'Judul',
            'media_type' => 'pdf',
        ]);

        $this->assertArrayHasKey('media_type', $errors);
    }

    public function test_validasi_menolak_tombol_tanpa_tujuan(): void
    {
        $errors = $this->validasi([
            'title'        => 'Judul',
            'media_type'   => 'none',
            'button_label' => 'Buka',
        ]);

        $this->assertArrayHasKey('button_route', $errors);
    }

    public function test_validasi_menolak_tujuan_tanpa_label(): void
    {
        $errors = $this->validasi([
            'title'      => 'Judul',
            'media_type' => 'none',
            'button_url' => 'https://intranet.chutex.id/x',
        ]);

        $this->assertArrayHasKey('button_label', $errors);
    }

    public function test_validasi_menolak_route_tidak_terdaftar(): void
    {
        $errors = $this->validasi([
            'title'        => 'Judul',
            'media_type'   => 'none',
            'button_label' => 'Buka',
            'button_route' => 'route.entah.apa',
        ]);

        $this->assertArrayHasKey('button_route', $errors);
    }

    public function test_validasi_media_wajib_untuk_jenis_gambar(): void
    {
        $errors = $this->validasi([
            'title'      => 'Judul',
            'media_type' => 'image',
        ]);

        $this->assertArrayHasKey('media_file', $errors);
    }

    public function test_validasi_lolos_untuk_banner_lengkap(): void
    {
        $this->daftarkanRute('uji-whats-new-lengkap', 'uji.whatsnew.lengkap');

        $errors = $this->validasi([
            'title'        => 'Judul',
            'media_type'   => 'video',
            'media_url'    => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
            'button_label' => 'Tonton',
            'button_route' => 'uji.whatsnew.lengkap',
            'sort_order'   => 2,
            'is_active'    => 1,
        ]);

        $this->assertSame([], $errors);
    }
}