<?php

namespace Tests\Unit;

use App\Models\WorkAssignmentRequest;
use App\Services\ApprovalWa\IzinDinasMessageFormatter;
use ReflectionClass;
use Tests\TestCase;

/**
 * Pesan Izin Dinas harus memberi approver jalan pintas yang bisa diklik.
 *
 * Gejala yang dilaporkan user (20/11/2026): pesan izin dinas hanya memuat
 * perintah teks `/approve-izin-dinas1`, tanpa link `wa.me`. Approver mengetik
 * perintah itu, gateway menjawab "Unknown command", dan keputusan tidak pernah
 * sampai ke Laravel. Pesan cuti sudah benar sejak awal karena memakai deep link.
 *
 * Aturan yang dijaga:
 *  - kedua aksi (setujui / tolak) memakai link `https://wa.me/<nomor bot>?text=`;
 *  - nomor bot diambil dari config `services.wa_gateway.bot_number` (satu nomor
 *    untuk cuti dan izin dinas, tidak di-hardcode di formatter);
 *  - teks command di dalam link identik dengan yang tertulis di badan pesan,
 *    jadi menyalin manual maupun menekan link menghasilkan keputusan yang sama.
 */
class IzinDinasMessageWaLinkTest extends TestCase
{
    private function pesan(int $id = 1): string
    {
        // Nama pemohon diambil dari master lewat EmployeeLookupService (koneksi
        // CII). Unit test tidak boleh menunggu koneksi DB, jadi cache nama diisi
        // langsung — yang diuji di sini format pesan, bukan lookup nama.
        $rc   = new ReflectionClass(IzinDinasMessageFormatter::class);
        $prop = $rc->getProperty('nameCache');
        $prop->setAccessible(true);
        $prop->setValue(null, ['C-00825' => 'CHRISTANTIE IMANUELA']);

        $request = new WorkAssignmentRequest();
        $request->forceFill([
            'id'              => $id,
            'npk'             => 'C-00825',
            'dinas_ke'        => 'Cabang Chutex',
            'keperluan'       => 'Meeting Supplier',
            'start_date'      => '2026-11-20',
            'end_date'        => '2026-11-20',
            'total_days'      => 1,
            'jam_keluar'      => '16:30',
            'rencana_kembali' => '17:00',
        ]);

        return IzinDinasMessageFormatter::formatApprovalRequest(
            $request,
            'DIMAS GALANG RAMADHAN',
            1,
            3
        );
    }

    public function test_pesan_memuat_link_wa_me_untuk_setujui_dan_tolak(): void
    {
        config(['services.wa_gateway.bot_number' => '62881082153848']);

        $pesan = $this->pesan();

        $this->assertSame(2, substr_count($pesan, 'https://wa.me/'), 'Setujui dan tolak masing-masing wajib punya link');
        $this->assertStringContainsString('https://wa.me/62881082153848?text=', $pesan);
    }

    public function test_nomor_bot_mengikuti_config_bukan_hardcode(): void
    {
        config(['services.wa_gateway.bot_number' => '628111222333']);

        $pesan = $this->pesan();

        $this->assertStringContainsString('https://wa.me/628111222333?text=', $pesan);
        $this->assertStringNotContainsString('62881082153848', $pesan);
    }

    public function test_teks_dalam_link_sama_dengan_command_di_badan_pesan(): void
    {
        config(['services.wa_gateway.bot_number' => '62881082153848']);

        $pesan = $this->pesan(7);

        preg_match_all('#https://wa\.me/\d+\?text=(\S+)#', $pesan, $m);
        $this->assertCount(2, $m[1], 'Harus ada dua link ber-text');

        // urlencode() memakai '+' untuk spasi (sama dengan pesan cuti yang sudah
        // terbukti jalan di WhatsApp), jadi decode-nya pakai urldecode().
        $approve = urldecode($m[1][0]);
        $reject  = urldecode($m[1][1]);

        $this->assertSame('/approve-izin-dinas7', $approve);
        $this->assertSame('/tolak-izin-dinas7 <alasan>', $reject);

        // Command yang sama juga muncul sebagai teks biasa untuk disalin manual.
        $this->assertStringContainsString('`/approve-izin-dinas7`', $pesan);
        $this->assertStringContainsString('`/tolak-izin-dinas7 <alasan>`', $pesan);
    }

    public function test_isi_pesan_izin_dinas_tidak_berubah(): void
    {
        config(['services.wa_gateway.bot_number' => '62881082153848']);

        $pesan = $this->pesan();

        foreach ([
            'IZIN DINAS — BUTUH PERSETUJUAN',
            '*DATA PEMOHON*',
            'CHRISTANTIE IMANUELA',
            'C-00825',
            '*DETAIL PERJALANAN*',
            'Cabang Chutex',
            'Meeting Supplier',
            '20/11/2026',
            '1 hari',
            '16:30',
            '17:00',
            '*#1*',
            '1 dari 3',
            'DIMAS GALANG RAMADHAN',
            'Mohon pastikan',
        ] as $harusAda) {
            $this->assertStringContainsString($harusAda, $pesan, "Pesan kehilangan bagian: $harusAda");
        }
    }
}