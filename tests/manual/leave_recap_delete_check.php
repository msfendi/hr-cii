<?php

/**
 * Verifikasi fitur HAPUS pengajuan di Leave Recap (per token).
 *
 * Menguji dua kondisi:
 *   A. Pengajuan sudah disetujui (saldo sudah terpotong) → saldo dikembalikan.
 *   B. Pengajuan masih pending → cukup dihapus, saldo tidak disentuh.
 *
 * Semua data uji dibersihkan di akhir.
 *
 * Jalankan: php8.3 artisan tinker --execute="require 'tests/manual/leave_recap_delete_check.php';"
 */

use App\Http\Controllers\AdminLeaveRecapController;
use App\Models\ApprovalDept;
use App\Models\ApprovalRule;
use App\Models\LeaveRequest;
use App\Models\Overtime;
use App\Services\ApprovalWa\LeaveApprovalEffects;
use Illuminate\Support\Facades\DB;

$fail = 0;
$npk  = 'TST-DEL-01';
$year = (int) date('Y');
$date = '2026-12-02'; // hari kerja (dipakai di uji sebelumnya)
$typeId = 5;          // Cuti Tahunan — memakai saldo

$assert = function (string $label, bool $ok) use (&$fail) {
    echo ($ok ? '  OK   ' : '  FAIL ') . $label . PHP_EOL;
    if (!$ok) {
        $fail++;
    }
};

$balance = fn () => DB::connection('cii')->table('leave_balances')
    ->where('NPK', $npk)->where('leave_type_id', $typeId)->where('year', $year)->first();

// ─── Bersihkan sisa run sebelumnya ───
$oldTokens = LeaveRequest::where('NPK', $npk)->pluck('token')->all();
LeaveRequest::whereIn('token', $oldTokens)->delete();
Overtime::where('NPK', $npk)->delete();
DB::connection('cii')->table('leave_balances')->where('NPK', $npk)->delete();
foreach (ApprovalDept::where('name', 'like', 'ZZ TEST DEL%')->pluck('id') as $oldId) {
    ApprovalRule::where('rules_id', $oldId)->delete();
    ApprovalDept::where('id', $oldId)->delete();
}

// ─── Group approval uji (2 level) ───
$g = ApprovalDept::create(['name' => 'ZZ TEST DEL', 'dept' => ['99999'], 'section' => null]);
foreach ([['TDL-A', 1], ['TDL-B', 2]] as [$approver, $lv]) {
    ApprovalRule::create(['rules_id' => $g->id, 'name' => 'Lv' . $lv, 'approval_id' => $approver, 'level' => $lv]);
}

// Saldo awal: 12 hari, terpakai 0
DB::connection('cii')->table('leave_balances')->insert([
    'NPK' => $npk, 'leave_type_id' => $typeId, 'year' => $year,
    'used_days' => 0, 'remained_days' => 12, 'negative_leave' => 0,
    'created_at' => now(), 'updated_at' => now(),
]);

$makeRequest = function () use ($g, $npk, $date, $typeId) {
    $token = 'ZDEL' . substr(md5(uniqid('', true)), 0, 12);

    foreach (ApprovalRule::where('rules_id', $g->id)->orderBy('level')->get() as $r) {
        LeaveRequest::create([
            'NPK' => $npk, 'leave_type_id' => $typeId, 'reason' => 'uji hapus', 'start_date' => $date,
            'end_date' => $date, 'total_days' => 1, 'approval_id' => $r->approval_id,
            'approval_level' => $r->level, 'approval_progress' => 1, 'status' => 'pending',
            'token' => $token, 'void' => 'false', 'rules_id' => $g->id,
        ]);
    }

    return $token;
};

echo '═══ SKENARIO A: pengajuan SUDAH disetujui → saldo dikembalikan ═══' . PHP_EOL;
$tA = $makeRequest();

// Set semua level approved, lalu finalisasi (persis seperti jalur approve normal).
LeaveRequest::where('token', $tA)->update(['status' => 'approved', 'approval_date' => now()]);
$anchor = LeaveRequest::where('token', $tA)->orderByDesc('approval_level')->first();
LeaveApprovalEffects::finalizeApproval($anchor);

$balAfterApprove = $balance();
echo '  saldo setelah disetujui: used=' . $balAfterApprove->used_days . ' remained=' . $balAfterApprove->remained_days . PHP_EOL;
$assert('saldo terpotong setelah disetujui', (int) $balAfterApprove->used_days === 1 && (int) $balAfterApprove->remained_days === 11);
$assert('Overtime CT terbentuk', Overtime::where('NPK', $npk)->where('OVERTIME_DATE', $date)->count() > 0);

$resA = app(AdminLeaveRecapController::class)->destroy($tA);
$payloadA = $resA->getData(true);
echo '  response: ' . json_encode($payloadA) . PHP_EOL;

$balRestored = $balance();
echo '  saldo setelah dihapus: used=' . $balRestored->used_days . ' remained=' . $balRestored->remained_days . PHP_EOL;

$assert('response sukses', ($payloadA['success'] ?? false) === true);
$assert('ditandai saldo dikembalikan', ($payloadA['restored_balance'] ?? false) === true);
$assert('semua baris token terhapus', LeaveRequest::where('token', $tA)->count() === 0);
$assert('saldo kembali ke semula (used=0, remained=12)', (int) $balRestored->used_days === 0 && (int) $balRestored->remained_days === 12);
$assert('Overtime CT dibatalkan', Overtime::where('NPK', $npk)->where('OVERTIME_DATE', $date)->whereIn('JUMLAH_JAM_LEMBUR', ['CT', 'CU'])->count() === 0);

echo PHP_EOL . '═══ SKENARIO B: pengajuan MASIH pending → tidak menyentuh saldo ═══' . PHP_EOL;
$tB = $makeRequest();
$balBeforeB = $balance();
echo '  saldo sebelum: used=' . $balBeforeB->used_days . ' remained=' . $balBeforeB->remained_days . PHP_EOL;

$resB = app(AdminLeaveRecapController::class)->destroy($tB);
$payloadB = $resB->getData(true);
echo '  response: ' . json_encode($payloadB) . PHP_EOL;

$balAfterB = $balance();
echo '  saldo sesudah: used=' . $balAfterB->used_days . ' remained=' . $balAfterB->remained_days . PHP_EOL;

$assert('response sukses', ($payloadB['success'] ?? false) === true);
$assert('TIDAK menandai saldo dikembalikan', ($payloadB['restored_balance'] ?? true) === false);
$assert('semua baris token terhapus', LeaveRequest::where('token', $tB)->count() === 0);
$assert('saldo tidak berubah', (int) $balAfterB->used_days === (int) $balBeforeB->used_days && (int) $balAfterB->remained_days === (int) $balBeforeB->remained_days);

echo PHP_EOL . '═══ SKENARIO C: token tidak ada → 404 ═══' . PHP_EOL;
$resC = app(AdminLeaveRecapController::class)->destroy('TOKEN-TIDAK-ADA');
$payloadC = $resC->getData(true);
echo '  http=' . $resC->getStatusCode() . ' body=' . json_encode($payloadC) . PHP_EOL;
$assert('status 404', $resC->getStatusCode() === 404);
$assert('success=false', ($payloadC['success'] ?? true) === false);

// ─── CLEANUP ───
echo PHP_EOL . '═══ CLEANUP ═══' . PHP_EOL;
$delOt = Overtime::where('NPK', $npk)->delete();
$delLr = LeaveRequest::where('NPK', $npk)->delete();
$delBal = DB::connection('cii')->table('leave_balances')->where('NPK', $npk)->delete();
$delRule = ApprovalRule::where('rules_id', $g->id)->delete();
$delDept = ApprovalDept::where('id', $g->id)->delete();

echo "  overtime: {$delOt}, leave_requests: {$delLr}, leave_balances: {$delBal}, rules: {$delRule}, depts: {$delDept}" . PHP_EOL;
echo '  sisa row uji: ' . LeaveRequest::where('NPK', $npk)->count() . PHP_EOL;

echo PHP_EOL . ($fail === 0 ? '✅ SEMUA SKENARIO LULUS' : "❌ {$fail} ASSERTION GAGAL") . PHP_EOL;

return $fail;
