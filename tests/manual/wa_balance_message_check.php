<?php

/**
 * Verifikasi blok SALDO CUTI pada pesan approval WhatsApp.
 *
 * Hanya menguji RENDER pesan — tidak ada pengiriman WhatsApp.
 *
 * Jalankan: php8.3 artisan tinker --execute="require 'tests/manual/wa_balance_message_check.php';"
 */

use App\Services\ApprovalWa\WaMessageFormatter;
use Illuminate\Support\Facades\DB;

$fail = 0;

$assert = function (string $label, bool $ok) use (&$fail) {
    echo ($ok ? '  OK   ' : '  FAIL ') . $label . PHP_EOL;
    if (!$ok) {
        $fail++;
    }
};

$year = (int) date('Y');

// Pakai NPK nyata yang punya saldo cuti tahunan, supaya angkanya realistis.
$bal = DB::connection('cii')->table('leave_balances')
    ->where('leave_type_id', 5)->where('year', $year)
    ->orderByDesc('remained_days')->first();

if (!$bal) {
    echo '  LEWAT: tidak ada saldo cuti tahunan di DB untuk diuji.' . PHP_EOL;

    return 0;
}

$npk      = (string) $bal->NPK;
$remained = (int) $bal->remained_days;

echo "NPK uji: {$npk} — saldo tahunan {$year}: {$remained} hari (terpakai {$bal->used_days})" . PHP_EOL;

/** Bikin objek request sederhana seperti baris leave_requests. */
$makeRequest = function (int $typeId, int $days) use ($npk, $year) {
    return (object) [
        'id'             => 999999,
        'token'          => 'TESTSALDO',
        'NPK'            => $npk,
        'leave_type_id'  => $typeId,
        'total_days'     => $days,
        'start_date'     => $year . '-06-15',
        'end_date'       => $year . '-06-17',
        'reason'         => 'keperluan keluarga',
        'created_at'     => now(),
        'approval_id'    => 'TDL-A',
        'approval_level' => 1,
    ];
};

$approver = (object) ['NPK' => 'TDL-A', 'NAMA' => 'APPROVER UJI'];

$render = function (int $typeId, int $days) use ($makeRequest, $approver) {
    return WaMessageFormatter::formatApprovalRequest($makeRequest($typeId, $days), $approver);
};

echo PHP_EOL . '═══ 1. Cuti Tahunan, saldo CUKUP (' . ($remained - 1) . ' dari ' . $remained . ') ═══' . PHP_EOL;
$msg1 = $render(5, max(1, $remained - 1));
echo $msg1 . PHP_EOL;

$assert('blok SALDO CUTI muncul', str_contains($msg1, 'SALDO CUTI TAHUNAN'));
$assert('menampilkan sisa saldo', str_contains($msg1, "Sisa saldo: *{$remained} hari*"));
$assert('menampilkan sisa setelah disetujui', str_contains($msg1, 'sisa setelah disetujui'));
$assert('TIDAK menyebut hutang cuti', !str_contains($msg1, 'HUTANG CUTI'));

echo PHP_EOL . '═══ 2. Cuti Tahunan, saldo HABIS/KURANG (butuh ' . ($remained + 2) . ' hari) ═══' . PHP_EOL;
$msg2 = $render(5, $remained + 2);
echo $msg2 . PHP_EOL;

$assert('blok SALDO CUTI muncul', str_contains($msg2, 'SALDO CUTI TAHUNAN'));
$assert('menandai kekurangan', str_contains($msg2, 'sisa saldo hanya'));
$assert('memakai keterangan HUTANG CUTI', str_contains($msg2, 'HUTANG CUTI'));
$assert('menyebut 2 hari kekurangan', str_contains($msg2, 'Kekurangan *2 hari*'));

echo PHP_EOL . '═══ 3. Bukan Cuti Tahunan (Cuti Menikah) → tanpa blok saldo ═══' . PHP_EOL;
$msg3 = $render(6, 3);
echo $msg3 . PHP_EOL;

$assert('TIDAK ada blok SALDO CUTI TAHUNAN', !str_contains($msg3, 'SALDO CUTI TAHUNAN'));
$assert('pesan inti tetap lengkap', str_contains($msg3, 'PENGAJUAN CUTI — BUTUH PERSETUJUAN') && str_contains($msg3, '/approve'));

echo PHP_EOL . '═══ 4. Cuti Tahunan tapi NPK tanpa saldo tahun ini ═══' . PHP_EOL;
$req4 = $makeRequest(5, 2);
$req4->NPK = 'TIDAK-ADA-NPK';
$msg4 = WaMessageFormatter::formatApprovalRequest($req4, $approver);
echo $msg4 . PHP_EOL;

$assert('blok muncul dengan pesan data belum tersedia', str_contains($msg4, 'data belum tersedia'));

echo PHP_EOL . ($fail === 0 ? '✅ SEMUA SKENARIO LULUS' : "❌ {$fail} ASSERTION GAGAL") . PHP_EOL;

return $fail;
