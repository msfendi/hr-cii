<?php

/**
 * Verifikasi ApprovalChainSync terhadap skenario nyata.
 *
 * Membuat group approval + pengajuan uji, menjalankan sync, memeriksa hasil,
 * lalu MENGHAPUS semua data uji (termasuk Overtime yang mungkin terbentuk).
 *
 * Jalankan: php8.3 artisan tinker --execute="require 'tests/manual/approval_chain_sync_check.php';"
 */

use App\Models\ApprovalDept;
use App\Models\ApprovalRule;
use App\Models\LeaveRequest;
use App\Models\Overtime;
use App\Services\Approval\ApprovalChainSync;
use Illuminate\Support\Facades\DB;

$created = ['dept' => [], 'tokens' => [], 'overtime_dates' => [], 'npk' => 'TST-SYNC-01'];
$fail = 0;

$assert = function (string $label, bool $ok) use (&$fail) {
    echo ($ok ? '  OK   ' : '  FAIL ') . $label . PHP_EOL;
    if (!$ok) {
        $fail++;
    }
};

// Bersihkan sisa run sebelumnya yang mungkin tertinggal
$oldTokens = LeaveRequest::where('NPK', $npk ?? 'TST-SYNC-01')->pluck('token')->all();
LeaveRequest::whereIn('token', $oldTokens)->delete();
foreach (ApprovalDept::where('name', 'like', 'ZZ TEST SYNC%')->pluck('id') as $oldId) {
    ApprovalRule::where('rules_id', $oldId)->delete();
    ApprovalDept::where('id', $oldId)->delete();
}

$makeGroup = function (string $suffix, array $approvers) use (&$created) {
    $g = ApprovalDept::create(['name' => 'ZZ TEST SYNC ' . $suffix, 'dept' => ['99999'], 'section' => null]);
    $created['dept'][] = $g->id;

    $lv = 1;
    foreach ($approvers as $npk) {
        ApprovalRule::create(['rules_id' => $g->id, 'name' => 'Lv' . $lv, 'approval_id' => $npk, 'level' => $lv]);
        $lv++;
    }

    return $g;
};

$makeRequest = function (int $groupId, string $npk = 'TST-SYNC-01', string $date = '2026-12-01') use (&$created) {
    $token = 'ZTEST' . substr(md5(uniqid('', true)), 0, 12);
    $created['tokens'][] = $token;
    $created['it_dates'][] = $date;

    foreach (ApprovalRule::where('rules_id', $groupId)->orderBy('level')->get() as $r) {
        LeaveRequest::create([
            'NPK' => $npk, 'leave_type_id' => 6, 'reason' => 'uji sinkronisasi', 'start_date' => $date,
            'end_date' => $date, 'total_days' => 1, 'approval_id' => $r->approval_id,
            'approval_level' => $r->level, 'approval_progress' => 1, 'status' => 'pending',
            'token' => $token, 'void' => 'false', 'rules_id' => $groupId,
        ]);
    }

    return $token;
};

$rows = fn (string $t) => LeaveRequest::where('token', $t)
    ->where('status', '<>', 'cancelled')
    ->orderBy('approval_level')
    ->get();

$shape = fn (string $t) => LeaveRequest::where('token', $t)
    ->orderBy('approval_level')
    ->get()
    ->map(fn ($r) => $r->approval_level . ':' . $r->approval_id . ':' . $r->status)
    ->implode(' | ');

// SQL Server mengembalikan kolom int sebagai string lewat PDO → selalu cast
// sebelum membandingkan, supaya uji tidak gagal karena tipe.
$levels = fn ($rows) => array_map('intval', $rows->pluck('approval_level')->all());
$npks   = fn ($rows) => array_map('strval', $rows->pluck('approval_id')->all());
$levelOf = function ($rows, string $npk) {
    $row = $rows->firstWhere('approval_id', $npk);

    return $row ? (int) $row->approval_level : null;
};

echo '═══ SKENARIO 1: level tengah dihapus (L2 dari 5) ═══' . PHP_EOL;
$g1 = $makeGroup('S1', ['T1-A', 'T1-B', 'T1-C', 'T1-D', 'T1-E']);
$t1 = $makeRequest($g1->id);
echo '  sebelum: ' . $shape($t1) . PHP_EOL;

ApprovalRule::where('rules_id', $g1->id)->where('approval_id', 'T1-B')->delete();
ApprovalChainSync::syncGroup($g1->id);
echo '  sesudah: ' . $shape($t1) . PHP_EOL;

$r1 = $rows($t1);
$assert('rantai rapat 1..4 tanpa celah', $levels($r1) === [1, 2, 3, 4]);
$assert('approver T1-B hilang dari chain', !in_array('T1-B', $npks($r1), true));
$assert('urutan approver benar', $npks($r1) === ['T1-A', 'T1-C', 'T1-D', 'T1-E']);
$assert('semua masih pending', $r1->every(fn ($r) => $r->status === 'pending'));
$assert('T1-B dicatat cancelled', LeaveRequest::where('token', $t1)->where('approval_id', 'T1-B')->value('status') === 'cancelled');

echo PHP_EOL . '═══ SKENARIO 2: level baru disisipkan di tengah ═══' . PHP_EOL;
$g2 = $makeGroup('S2', ['T2-A', 'T2-C']);
$t2 = $makeRequest($g2->id);
echo '  sebelum: ' . $shape($t2) . PHP_EOL;

ApprovalRule::where('rules_id', $g2->id)->where('approval_id', 'T2-C')->update(['level' => 3]);
ApprovalRule::create(['rules_id' => $g2->id, 'name' => 'Lv2', 'approval_id' => 'T2-B', 'level' => 2]);
ApprovalChainSync::syncGroup($g2->id);
echo '  sesudah: ' . $shape($t2) . PHP_EOL;

$r2 = $rows($t2);
$assert('approver baru T2-B muncul', in_array('T2-B', $npks($r2), true));
$assert('urutan jadi A,B,C', $npks($r2) === ['T2-A', 'T2-B', 'T2-C']);
$assert('total 3 level', $r2->count() === 3);

echo PHP_EOL . '═══ SKENARIO 3: L1 sudah approved, L2 dihapus ═══' . PHP_EOL;
$g3 = $makeGroup('S3', ['T3-A', 'T3-B', 'T3-C']);
$t3 = $makeRequest($g3->id);

LeaveRequest::where('token', $t3)->where('approval_id', 'T3-A')
    ->update(['status' => 'approved', 'approval_date' => now()]);

echo '  sebelum: ' . $shape($t3) . PHP_EOL;
ApprovalRule::where('rules_id', $g3->id)->where('approval_id', 'T3-B')->delete();
ApprovalChainSync::syncGroup($g3->id);
echo '  sesudah: ' . $shape($t3) . PHP_EOL;

$r3 = $rows($t3);
$assert('L1 tetap approved (tidak diubah)', $r3->firstWhere('approval_id', 'T3-A')->status === 'approved');
$assert('rantai rapat 1..2', $levels($r3) === [1, 2]);
$assert('T3-C naik ke level 2', $levelOf($r3, 'T3-C') === 2);
$assert('progress menunjuk level 2', (int) $r3->first()->approval_progress === 2);

echo PHP_EOL . '═══ SKENARIO 4: pengajuan selesai (semua approved) tidak disentuh ═══' . PHP_EOL;
$g4 = $makeGroup('S4', ['T4-A', 'T4-B']);
$t4 = $makeRequest($g4->id);

LeaveRequest::where('token', $t4)->update(['status' => 'approved', 'approval_date' => now()]);
$before4 = $shape($t4);

ApprovalRule::where('rules_id', $g4->id)->where('approval_id', 'T4-B')->delete();
ApprovalChainSync::syncGroup($g4->id);
$after4 = $shape($t4);

echo '  sebelum: ' . $before4 . PHP_EOL;
echo '  sesudah: ' . $after4 . PHP_EOL;
$assert('pengajuan selesai TIDAK diubah', $before4 === $after4);

echo PHP_EOL . '═══ SKENARIO 5: L1 approved, L2 & L3 dihapus → finalisasi ═══' . PHP_EOL;
$g5 = $makeGroup('S5', ['T5-A', 'T5-B', 'T5-C']);
$t5 = $makeRequest($g5->id, 'TST-SYNC-01', '2026-12-02');

LeaveRequest::where('token', $t5)->where('approval_id', 'T5-A')
    ->update(['status' => 'approved', 'approval_date' => now()]);

echo '  sebelum: ' . $shape($t5) . PHP_EOL;
ApprovalRule::where('rules_id', $g5->id)->whereIn('approval_id', ['T5-B', 'T5-C'])->delete();
ApprovalChainSync::syncGroup($g5->id);
echo '  sesudah: ' . $shape($t5) . PHP_EOL;

$r5 = $rows($t5);
$assert('sisa row approved', $r5->every(fn ($r) => $r->status === 'approved'));
$otCount = Overtime::where('NPK', 'TST-SYNC-01')->where('OVERTIME_DATE', '2026-12-02')->count();
$assert('Overtime CT terbentuk (finalisasi jalan)', $otCount > 0);

echo PHP_EOL . '═══ SKENARIO 6: syncAllPending tidak error ═══' . PHP_EOL;
$stats = ApprovalChainSync::syncAllPending();
echo '  stats: ' . json_encode($stats) . PHP_EOL;
$assert('syncAllPending berjalan', isset($stats['checked']));

echo PHP_EOL . '═══ SKENARIO 7: sisipkan level baru di tengah (makeRoomForLevel) ═══' . PHP_EOL;
$g7 = $makeGroup('S7', ['T7-A', 'T7-B', 'T7-C']);
$master7 = fn () => array_map('intval', ApprovalRule::where('rules_id', $g7->id)->orderByRaw('CAST(level AS INT)')->pluck('level')->all());
$order7  = fn () => ApprovalRule::where('rules_id', $g7->id)->orderByRaw('CAST(level AS INT)')->pluck('approval_id')->all();
echo '  sebelum: level=' . implode(',', $master7()) . ' order=' . implode(',', $order7()) . PHP_EOL;

ApprovalChainSync::makeRoomForLevel($g7->id, 2);
ApprovalRule::create(['rules_id' => $g7->id, 'name' => 'Lv2', 'approval_id' => 'T7-NEW', 'level' => 2]);
ApprovalChainSync::normalizeGroupLevels($g7->id);
echo '  sesudah: level=' . implode(',', $master7()) . ' order=' . implode(',', $order7()) . PHP_EOL;

$assert('level tetap rapat 1..4', $master7() === [1, 2, 3, 4]);
$assert('approver baru di posisi 2', $order7() === ['T7-A', 'T7-NEW', 'T7-B', 'T7-C']);

echo PHP_EOL . '═══ SKENARIO 8: hapus level di master → level rapat ═══' . PHP_EOL;
$g8 = $makeGroup('S8', ['T8-A', 'T8-B', 'T8-C', 'T8-D']);
echo '  sebelum: level=' . implode(',', array_map('intval', ApprovalRule::where('rules_id', $g8->id)->orderByRaw('CAST(level AS INT)')->pluck('level')->all())) . PHP_EOL;

ApprovalRule::where('rules_id', $g8->id)->where('approval_id', 'T8-B')->delete();
ApprovalChainSync::normalizeGroupLevels($g8->id);
$after8 = array_map('intval', ApprovalRule::where('rules_id', $g8->id)->orderByRaw('CAST(level AS INT)')->pluck('level')->all());
echo '  sesudah: level=' . implode(',', $after8) . PHP_EOL;
$assert('level rapat 1..3 (tanpa celah)', $after8 === [1, 2, 3]);

echo PHP_EOL . '═══ SKENARIO 9: pindah rule dari L1 ke L3 ═══' . PHP_EOL;
$g9 = $makeGroup('S9', ['T9-A', 'T9-B', 'T9-C']);
$order9 = fn () => ApprovalRule::where('rules_id', $g9->id)->orderByRaw('CAST(level AS INT)')->pluck('approval_id')->all();
echo '  sebelum: ' . implode(',', $order9()) . PHP_EOL;

$move = ApprovalRule::where('rules_id', $g9->id)->where('approval_id', 'T9-A')->first();
$move->level = 999999;
$move->save();
ApprovalChainSync::normalizeGroupLevels($g9->id);
ApprovalChainSync::makeRoomForLevel($g9->id, 3, (int) $move->id);
$move->level = 3;
$move->save();
ApprovalChainSync::normalizeGroupLevels($g9->id);
echo '  sesudah: ' . implode(',', $order9()) . PHP_EOL;

$levels9 = array_map('intval', ApprovalRule::where('rules_id', $g9->id)->orderByRaw('CAST(level AS INT)')->pluck('level')->all());
$order9Arr = $order9();
$assert('level rapat 1..3', $levels9 === [1, 2, 3]);
$assert('T9-A pindah ke urutan terakhir', end($order9Arr) === 'T9-A');

// ─────────── CLEANUP ───────────
echo PHP_EOL . '═══ CLEANUP ═══' . PHP_EOL;

$delOt = Overtime::where('NPK', 'TST-SYNC-01')->delete();
$delLr = LeaveRequest::whereIn('token', $created['tokens'])->orWhere('NPK', 'TST-SYNC-01')->delete();
$delRule = 0;
$delDept = 0;

foreach ($created['dept'] as $id) {
    $delRule += ApprovalRule::where('rules_id', $id)->delete();
    $delDept += ApprovalDept::where('id', $id)->delete();
}

echo "  overtime dihapus: {$delOt}" . PHP_EOL;
echo "  leave_requests dihapus: {$delLr}" . PHP_EOL;
echo "  approval_rules dihapus: {$delRule}" . PHP_EOL;
echo "  approval_depts dihapus: {$delDept}" . PHP_EOL;
echo '  sisa row uji: ' . LeaveRequest::where('NPK', 'TST-SYNC-01')->count() . PHP_EOL;

echo PHP_EOL . ($fail === 0 ? '✅ SEMUA SKENARIO LULUS' : "❌ {$fail} ASSERTION GAGAL") . PHP_EOL;

return $fail;
