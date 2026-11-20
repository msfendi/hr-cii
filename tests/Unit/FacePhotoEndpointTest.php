<?php

namespace Tests\Unit;

use App\Http\Controllers\FaceRecognitionController;
use App\Models\EmployeeFace;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Endpoint foto wajah di Manage Face Recognition.
 *
 * Latar belakang: foto disimpan sebagai data URI base64 di kolom face_img.
 * Sebelumnya seluruh foto ikut dirender di tabel sehingga halaman mengangkut
 * ratusan foto sekaligus. Sekarang tabel hanya memuat tombol, dan foto
 * diambil lewat endpoint ini saat modal dibuka.
 *
 * Uji ini tidak menyentuh database: model dibuat in-memory, controller dipanggil
 * langsung. Yang dijaga: hanya data URI gambar yang sah yang disajikan, dengan
 * content-type sesuai, dan permintaan tak sah ditolak 404.
 */
class FacePhotoEndpointTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // abort() dan response() membutuhkan container Laravel; uji ini tidak
        // memakai database sama sekali, hanya bootstrap aplikasi.
        $container = \Illuminate\Container\Container::getInstance();

        if ($container === null || ! $container->bound('config')) {
            $app = require dirname(__DIR__, 2) . '/bootstrap/app.php';
            $app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
        }
    }

    private function model(string $dataUri): EmployeeFace
    {
        return new EmployeeFace(['npk' => 'C-00001', 'face_img' => $dataUri]);
    }

    public function test_foto_base64_png_disajikan_dengan_content_type_benar(): void
    {
        // 1x1 piksel PNG transparan.
        $png = base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg=='
        );

        $respon = (new FaceRecognitionController())->photo(
            $this->model('data:image/png;base64,' . base64_encode($png))
        );

        $this->assertSame(200, $respon->getStatusCode());
        $this->assertSame('image/png', $respon->headers->get('Content-Type'));
        $this->assertSame($png, $respon->getContent());
        $this->assertStringContainsString('private', (string) $respon->headers->get('Cache-Control'));
    }

    public function test_foto_jpg_dinormalkan_menjadi_image_jpeg(): void
    {
        $respon = (new FaceRecognitionController())->photo(
            $this->model('data:image/jpg;base64,' . base64_encode('bukan-gambar-asli'))
        );

        $this->assertSame('image/jpeg', $respon->headers->get('Content-Type'));
    }

    public function test_foto_kosong_ditolak_404(): void
    {
        $this->expectException(NotFoundHttpException::class);

        (new FaceRecognitionController())->photo($this->model(''));
    }

    public function test_data_uri_bukan_gambar_ditolak_404(): void
    {
        $this->expectException(NotFoundHttpException::class);

        (new FaceRecognitionController())->photo($this->model('data:text/plain;base64,SGVsbG8='));
    }

    /**
     * Penjaga regresi: foto TIDAK boleh kembali dirender langsung di tabel.
     * Bila suatu saat ada yang mengembalikan <img src="{{ $face->face_img }}">
     * di kolom Foto, uji ini gagal.
     */
    public function test_tabel_tidak_menempelkan_base64_foto(): void
    {
        $berkas = dirname(__DIR__, 2) . '/resources/views/face_recognition/index.blade.php';
        $isi    = (string) file_get_contents($berkas);

        $this->assertStringNotContainsString('data-src="{{ $face->face_img }}"', $isi);
        $this->assertStringNotContainsString('data-full="{{ $face->face_img }}"', $isi);

        // Tombol aksi yang memicu modal harus ada, dan hanya membawa URL.
        $this->assertStringContainsString('btn-preview-photo', $isi);
        $this->assertStringContainsString("route('face-recognition.photo', \$face)", $isi);

        // Foto dimuat saat modal tampil, bukan saat halaman dirender.
        $this->assertStringContainsString("jQuery('#facePhotoModal').on('show.bs.modal'", $isi);
    }

    public function test_tabel_diurutkan_dari_wajah_terbaru(): void
    {
        $berkas = dirname(__DIR__, 2) . '/resources/views/face_recognition/index.blade.php';
        $isi    = (string) file_get_contents($berkas);

        $this->assertStringContainsString('data-order="{{ $face->updated_at', $isi);
        $this->assertStringContainsString("order: [[0, 'desc']]", $isi);
    }
}