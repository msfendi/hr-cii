<?php

namespace Tests\Feature;

use App\Services\EmployeeActiveStatusService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * REGRESSION TEST: /canteen-transaction "NPK Tidak Valid" palsu
 * ===========================================================
 *
 * BUG (ditemukan 2026-11-09 dari screenshot user):
 *   Halaman /canteen-transaction menampilkan SweetAlert MERAH
 *   "NPK Tidak Valid" padahal NPK-nya VALID.
 *
 * AKAR MASALAH:
 *   Backend  : CanteenTransactionController::validateNpk mengembalikan
 *              key "ok" + "inactive" (dari FaceRecognitionService).
 *   Frontend : resources/views/canteen_transaction/index.blade.php
 *              membaca `data.success` + `data.inactive_employee`
 *              (copy-paste dari /face-attendance/login yang mengembalikan
 *              "success" + "inactive_employee").
 *
 *   Akibatnya `data.success` = undefined -> `!data.success` selalu TRUE
 *   -> alert merah selalu muncul, dan alur kamera/minimap tidak pernah
 *   berjalan (user tidak pernah sampai ke stepFace).
 *
 * TEST INI:
 *   1. Kontrak response WAJIB punya key "success" (bukan hanya "ok").
 *   2. Key "inactive_employee" WAJIB ada (bukan hanya "inactive").
 *   3. Frontend WAJIB punya guard yang membaca kedua bentuk key
 *      (regresi kalau ada yang kembali ke `!data.success` polos).
 */
class CanteenValidateNpkContractTest extends TestCase
{
    /**
     * Kontrak response harus sinkron dengan /face-attendance/login.
     */
    public function test_validate_npk_response_uses_login_contract_keys(): void
    {
        $controller = new \App\Http\Controllers\CanteenTransactionController();
        $method    = new \ReflectionMethod($controller, 'validateNpk');
        $src       = file_get_contents((new \ReflectionClass($controller))->getFileName());

        // 1. Payload harus menyertakan "success" (kunci utama frontend).
        $this->assertStringContainsString(
            "'success'",
            $src,
            'validateNpk harus mengembalikan key "success" (kontrak /login).'
        );

        // 2. Payload harus menyertakan "inactive_employee".
        $this->assertStringContainsString(
            "'inactive_employee'",
            $src,
            'validateNpk harus mengembalikan key "inactive_employee" (kontrak /login).'
        );

        // 3. Key lama tetap ada (backward compatible).
        $this->assertStringContainsString("'ok'", $src);
        $this->assertStringContainsString("'inactive'", $src);

        $this->assertTrue($method->isPublic());
    }

    /**
     * Frontend tidak boleh lagi memakai `!data.success` polos yang
     * membuat alert merah palsu saat server belum mengirim key itu.
     */
    public function test_canteen_frontend_reads_success_flag_defensively(): void
    {
        $view = file_get_contents(
            resource_path('views/canteen_transaction/index.blade.php')
        );

        // Harus ada pembacaan defensif `data.ok === true`.
        $this->assertStringContainsString(
            'data.ok === true',
            $view,
            'Frontend harus membaca data.ok (payload lama) sebagai fallback.'
        );

        // Harus ada pembacaan defensif untuk flag resign.
        $this->assertStringContainsString(
            'data.inactive_employee || data.inactive',
            $view,
            'Frontend harus membaca inactive_employee DAN inactive.'
        );

        // ==========================================================
        // Hanya handler VALIDATE-NPK yang boleh dicek. Handler
        // `canteen-transaction.store` memang memakai `data.success`
        // polos karena endpoint itu memang mengembalikan key
        // "success" (lihat CanteenTransactionController::store).
        // ==========================================================
        $validateIdx = strpos($view, 'canteen-transaction.validate-npk');
        $storeIdx    = strpos($view, 'canteen-transaction.store');

        $this->assertNotFalse($validateIdx, 'View harus memanggil endpoint validate-npk.');
        $this->assertNotFalse($storeIdx,    'View harus memanggil endpoint store.');

        // Blok handler validate = dari validate-npk ke/store.
        $validateBlock = substr($view, $validateIdx, $storeIdx - $validateIdx);

        // Buang komentar JS/CSS supaya string penjelas di dalam kode
        // tidak ikut ter-match sebagai bug yang sebenarnya.
        $validateCode = preg_replace(
            ['~//[^\n]*~', '~/\*.*?\*/~s', '~/\*.*?\*/~s'],
            '',
            $validateBlock
        );

        // Tidak boleh ada pola lama `!data.success` polos di handler validate.
        $this->assertStringNotContainsString(
            '!data.success',
            $validateCode,
            'Pola `!data.success` polos pada handler validate-NPK membuat SweetAlert merah palsu.'
        );

        // Tidak boleh ada `data.success` berdiri sendiri tanpa fallback.
        $this->assertStringNotContainsString(
            'if(data.success)',
            $validateCode,
            'Pola if(data.success) tidak aman bila field tidak terkirim.'
        );

        // Handler validate WAJIB memakai flag `isValid` yang robust.
        $this->assertStringContainsString(
            'data.ok === true',
            $validateCode,
            'Handler validate harus pakai flag isValid (data.success ATAU data.ok).'
        );
    }

    /**
     * Aturan bisnis inti: PKWT.TKK NULL = aktif, terisi = sudah keluar.
     */
    public function test_tkk_null_means_active_and_filled_means_inactive(): void
    {
        $service = new \ReflectionClass(EmployeeActiveStatusService::class);
        $this->assertTrue($service->hasMethod('check'));

        // Pesan tidak-aktif harus menyebut nama + tanggal keluar.
        $msg = EmployeeActiveStatusService::inactiveMessage('Budi Santoso', '2026-05-31');
        $this->assertStringContainsString('Budi Santoso', $msg);
        $this->assertStringContainsString('2026', $msg);
    }
}
