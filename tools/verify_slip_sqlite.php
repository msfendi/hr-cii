<?php
/**
 * Uji nyata: daftarSlip() HrisV2Controller memakai DB referensi SQLite
 * hasil konversi struktur SQL Server CII (CII.sql).
 *
 * Jalankan:
 *   DB_CII_CONNECTION=sqlite DB_DATABASE=/tmp/cii_reference.sqlite php /tmp/verify_slip_sqlite.php
 */

require '/home/hermes/workspace/hr-cii/vendor/autoload.php';
$app = require '/home/hermes/workspace/hr-cii/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

// Referensi SQLite: paksa koneksi uji (tanpa menyentuh .env / produksi).
$dbUji = getenv('DB_REFERENCE_SQLITE') ?: '/tmp/cii_reference.sqlite';
config([
    'database.default' => 'cii',
    'database.connections.cii' => [
        'driver' => 'sqlite',
        'database' => $dbUji,
        'prefix' => '',
        'foreign_key_constraints' => false,
    ],
]);
DB::purge('cii');

$lulus = 0;
$gagal = 0;
function cek(string $judul, bool $ok, string $detail = ''): void
{
    global $lulus, $gagal;
    if ($ok) { $lulus++; echo "  [PASS] $judul".($detail !== '' ? " — $detail" : '')."\n"; }
    else { $gagal++; echo "  [FAIL] $judul".($detail !== '' ? " — $detail" : '')."\n"; }
}

echo "== 0. Koneksi uji ==\n";
echo "  driver : ".config('database.connections.cii.driver')."\n";
echo "  database: ".config('database.connections.cii.database')."\n";
cek('driver aktif sqlite (bukan produksi)', config('database.connections.cii.driver') === 'sqlite');
cek('file referensi ada', is_file(config('database.connections.cii.database')));

echo "\n== 1. Struktur tabel payroll di skema referensi ==\n";
foreach (['payroll_run_details', 'payroll_runs', 'payroll_periods'] as $t) {
    $kolom = array_map(fn ($c) => $c->name, DB::connection('cii')->select("PRAGMA table_info($t)"));
    cek("tabel $t terbaca", $kolom !== [], count($kolom).' kolom');
}
$kolomDetail = array_map(fn ($c) => $c->name, DB::connection('cii')->select('PRAGMA table_info(payroll_run_details)'));
cek('kolom employee_npk ada (bukan NPK)', in_array('employee_npk', $kolomDetail, true));
cek('kolom NPK tidak ada', ! in_array('NPK', $kolomDetail, true));

echo "\n== 2. Siapkan data uji ==\n";
DB::connection('cii')->table('payroll_run_details')->delete();
DB::connection('cii')->table('payroll_runs')->delete();
DB::connection('cii')->table('payroll_periods')->delete();

$periode = [
    ['id' => 1, 'name' => 'Payroll Januari 2026', 'start_date' => '2026-01-01', 'end_date' => '2026-01-31', 'is_closed' => 1],
    ['id' => 2, 'name' => 'Payroll Februari 2026', 'start_date' => '2026-02-01', 'end_date' => '2026-02-28', 'is_closed' => 1],
    ['id' => 3, 'name' => 'Payroll Maret 2026', 'start_date' => '2026-03-01', 'end_date' => '2026-03-31', 'is_closed' => 0],
];
DB::connection('cii')->table('payroll_periods')->insert($periode);

$run = [
    ['id' => 10, 'period_id' => 1, 'processed_at' => '2026-02-01 08:00:00', 'total_payroll' => 5000000, 'employee_count' => 1, 'progress' => 100, 'status' => 'done'],
    ['id' => 11, 'period_id' => 2, 'processed_at' => '2026-03-01 08:00:00', 'total_payroll' => 5200000, 'employee_count' => 2, 'progress' => 100, 'status' => 'done'],
    ['id' => 12, 'period_id' => 3, 'processed_at' => '2026-04-01 08:00:00', 'total_payroll' => 5300000, 'employee_count' => 1, 'progress' => 40, 'status' => 'running'],
];
DB::connection('cii')->table('payroll_runs')->insert($run);

DB::connection('cii')->table('payroll_run_details')->insert([
    ['id' => 100, 'run_id' => 10, 'employee_npk' => 'C-00827', 'employee_name' => 'DIMAS GALANG RAMADHAN', 'components' => '{}', 'total_salary' => 5000000, 'employee_dept' => 'IT'],
    ['id' => 101, 'run_id' => 11, 'employee_npk' => 'C-00827', 'employee_name' => 'DIMAS GALANG RAMADHAN', 'components' => '{}', 'total_salary' => 5200000, 'employee_dept' => 'IT'],
    ['id' => 102, 'run_id' => 12, 'employee_npk' => 'C-00827', 'employee_name' => 'DIMAS GALANG RAMADHAN', 'components' => '{}', 'total_salary' => 5300000, 'employee_dept' => 'IT'],
    ['id' => 103, 'run_id' => 11, 'employee_npk' => 'C-00001', 'employee_name' => 'KARYAWAN LAIN', 'components' => '{}', 'total_salary' => 4000000, 'employee_dept' => 'SEWING'],
]);
echo "  3 periode, 3 run, 4 detail siap\n";

echo "\n== 3. Kendali negatif: query LAMA (prd.NPK) harus GAGAL ==\n";
try {
    // Replika persis query produksi yang error: join sama, hanya kolom NPK yang dipakai.
    DB::connection('cii')->table('payroll_run_details as prd')
        ->join('payroll_runs as pr', 'pr.id', '=', 'prd.run_id')
        ->join('payroll_periods as pp', 'pp.id', '=', 'pr.period_id')
        ->where('prd.NPK', 'C-00827')
        ->where('pp.is_closed', 1)
        ->select('pr.id as run_id')
        ->get();
    cek('query lama gagal', false, 'justru berhasil — skema uji tidak mewakili produksi');
} catch (\Throwable $e) {
    $pesan = $e->getMessage();
    cek('query lama gagal pada kolom NPK', str_contains($pesan, 'NPK'), substr($pesan, 0, 100));
}

echo "\n== 4. daftarSlip() dengan kolom benar ==\n";
$controller = $app->make(App\Http\Controllers\HrisV2Controller::class);
$metode = new ReflectionMethod($controller, 'daftarSlip');
$metode->setAccessible(true);
$hasil = $metode->invoke($controller, 'C-00827');

echo "  tersedia : ".var_export($hasil['tersedia'], true)."\n";
echo "  catatan  : ".($hasil['catatan'] ?? '-')."\n";
foreach ($hasil['items'] as $i => $s) {
    echo "  #".($i + 1)." {$s['periode']} ({$s['rentang']}) -> {$s['url']}\n";
}

cek('data terbaca (tersedia=true)', $hasil['tersedia'] === true);
cek('jumlah slip = 2 (hanya periode ditutup)', count($hasil['items']) === 2, (string) count($hasil['items']));
cek('urutan terbaru dulu (Februari lalu Januari)', ($hasil['items'][0]['periode'] ?? '') === 'Payroll Februari 2026' && ($hasil['items'][1]['periode'] ?? '') === 'Payroll Januari 2026');
cek('periode belum ditutup tidak muncul', ! in_array('Payroll Maret 2026', array_column($hasil['items'], 'periode'), true));
cek('slip karyawan lain tidak bocor', count(array_filter($hasil['items'], fn ($s) => str_contains($s['url'], 'C-00001'))) === 0);
cek('url memuat run_id benar', str_contains($hasil['items'][0]['url'] ?? '', '/employee-payroll/show/11/C-00827'));

echo "\n== 5. Presensi wajah & menu lain tetap utuh ==\n";
cek('tabel face_attendance_logs ada', DB::connection('cii')->select("SELECT name FROM sqlite_master WHERE type='table' AND name='face_attendance_logs'") !== []);
cek('tabel leave_requests ada', DB::connection('cii')->select("SELECT name FROM sqlite_master WHERE type='table' AND name='leave_requests'") !== []);

echo "\n== Ringkasan: $lulus lulus, $gagal gagal ==\n";
exit($gagal === 0 ? 0 : 1);
