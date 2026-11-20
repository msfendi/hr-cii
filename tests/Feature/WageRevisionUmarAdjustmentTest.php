<?php

namespace Tests\Feature;

use App\Exports\WageRevision\WageRevisionResultExport;
use App\Imports\WageRevisionItemImport;
use App\Models\EmployeeWageRevision;
use App\Models\WageRevisionBatch;
use App\Models\WageRevisionItem;
use App\Models\WageRevisionRule;
use App\Services\WageRevisionAccess;
use App\Services\WageRevisionApplier;
use App\Services\WageRevisionBuilder;
use App\Services\WageRevisionCalculator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Maatwebsite\Excel\Facades\Excel;
use Tests\TestCase;

/**
 * Uji fungsional fitur penyesuaian upah tahunan (T1-T13).
 *
 * Aplikasi memakai dua koneksi: tabel batch di koneksi default, employees_contract di koneksi
 * `cii`. Di test ini keduanya diganti SQLite lokal supaya jalur nyata (generate, isi/import gaji
 * staff, setujui, terapkan, batalkan) bisa dijalankan tanpa menyentuh database produksi.
 */
class WageRevisionUmarAdjustmentTest extends TestCase
{
    private string $appDb;
    private string $ciiDb;

    protected function setUp(): void
    {
        parent::setUp();

        $this->appDb = database_path('wage_test_app.sqlite');
        $this->ciiDb = database_path('wage_test_cii.sqlite');
        foreach ([$this->appDb, $this->ciiDb] as $f) {
            if (file_exists($f)) {
                unlink($f);
            }
            touch($f);
        }

        config([
            'database.default' => 'wage_test',
            'database.connections.wage_test' => [
                'driver' => 'sqlite', 'database' => $this->appDb, 'prefix' => '', 'foreign_key_constraints' => false,
            ],
            'database.connections.cii' => [
                'driver' => 'sqlite', 'database' => $this->ciiDb, 'prefix' => '', 'foreign_key_constraints' => false,
            ],
        ]);

        DB::purge('wage_test');
        DB::purge('cii');

        foreach ([
            '2026_11_18_100001_create_wage_revision_batches_table.php',
            '2026_11_18_100002_create_wage_revision_rules_table.php',
            '2026_11_18_100003_create_wage_revision_items_table.php',
            '2026_11_18_100004_create_employee_wage_revisions_table.php',
        ] as $file) {
            (require database_path('migrations/' . $file))->up();
        }

        $this->createCiiSchema();
        $this->seedEmployees();
    }

    protected function tearDown(): void
    {
        foreach ([$this->appDb, $this->ciiDb] as $f) {
            if (file_exists($f)) {
                unlink($f);
            }
        }

        parent::tearDown();
    }

    // ------------------------------------------------------------------ helpers

    private function createCiiSchema(): void
    {
        Schema::connection('cii')->create('PKWT', function (Blueprint $t) {
            $t->string('NPK', 50)->primary();
            $t->date('TMK')->nullable();
            $t->date('TKK')->nullable();
        });

        foreach (['BIODATA', 'BIODATA_KELUAR'] as $table) {
            Schema::connection('cii')->create($table, function (Blueprint $t) {
                $t->string('NPK', 50);
                $t->string('ID_DEPT', 20)->nullable();
                $t->string('SECTION', 50)->nullable();
                $t->string('NAMA_KARYAWAN', 100)->nullable();
                $t->string('IS_STAFF', 5)->default('0');
                $t->string('IS_EXPAT', 5)->default('0');
            });
        }

        Schema::connection('cii')->create('DEPT', function (Blueprint $t) {
            $t->string('ID_DEPT', 20)->primary();
            $t->string('DEPARTEMENT', 100)->nullable();
            $t->string('IS_SEWING', 5)->default('0');
        });

        Schema::connection('cii')->create('employees_contract', function (Blueprint $t) {
            $t->string('id', 64)->primary();
            $t->string('npk', 50);
            $t->string('type', 20)->default('Contract');
            $t->decimal('salary', 18, 2)->default(0);
            $t->decimal('daily_salary', 18, 2)->default(0);
            $t->decimal('allowance', 18, 2)->default(0);
            $t->decimal('pph21', 18, 2)->default(0);
            $t->integer('contract_ke')->default(1);
            $t->date('start_date');
            $t->date('end_date');
            $t->string('status_contract', 20)->default('AKTIF');
            $t->timestamp('updated_at')->nullable();
        });
    }

    private function seedEmployees(): void
    {
        $dept = [
            ['ID_DEPT' => '1', 'DEPARTEMENT' => 'UTILITY', 'IS_SEWING' => '0'],
            ['ID_DEPT' => '2', 'DEPARTEMENT' => 'SEWING', 'IS_SEWING' => '1'],
        ];
        DB::connection('cii')->table('DEPT')->insert($dept);

        // npk => [dept, is_staff, is_expat, tmkk, tkk]
        $people = [
            ['C-01001', '2', '0', '0', '2020-01-01', null],
            ['C-01002', '1', '0', '0', '2021-01-01', null],
            ['C-01003', '1', '1', '0', '2019-01-01', null],
            ['C-01004', '1', '0', '1', '2019-06-01', null],
            ['C-01005', '1', '0', '0', '2022-01-01', null],
            ['C-01006', '1', '0', '0', '2015-01-01', '2026-06-30'],
            ['C-01007', '1', '0', '0', '2023-01-01', null],
            ['C-00017', '1', '0', '0', '2020-01-01', null],
        ];

        foreach ($people as $i => [$npk, $deptId, $staff, $expat, $tmk, $tkk]) {
            DB::connection('cii')->table('PKWT')->insert([
                'NPK' => $npk, 'TMK' => $tmk, 'TKK' => $tkk,
            ]);
            DB::connection('cii')->table('BIODATA')->insert([
                'NPK' => $npk, 'ID_DEPT' => $deptId, 'SECTION' => '109',
                'NAMA_KARYAWAN' => 'KARYAWAN UJI ' . ($i + 1),
                'IS_STAFF' => $staff, 'IS_EXPAT' => $expat,
            ]);
        }

        $contracts = [
            // id, npk, type, salary, daily, allowance, ke, start, end
            ['CT-1001', 'C-01001', 'Contract', 4000000, 0, 150000, 1, '2025-01-01', '2025-12-31'],
            ['CT-1002', 'C-01001', 'Contract', 4500000, 0, 200000, 2, '2026-01-01', '2027-12-31'],
            ['CT-1003', 'C-01002', 'Daily', 0, 180000, 0, 1, '2026-01-01', '2027-12-31'],
            ['CT-1004', 'C-01003', 'Contract', 8000000, 0, 500000, 1, '2026-01-01', '2027-12-31'],
            ['CT-1005', 'C-01004', 'Contract', 15000000, 0, 1000000, 1, '2026-01-01', '2027-12-31'],
            ['CT-1006', 'C-01005', 'Contract', 2000000, 0, 100000, 1, '2026-01-01', '2027-12-31'],
            ['CT-1007', 'C-01006', 'Contract', 3000000, 0, 0, 1, '2025-01-01', '2026-12-31'],
            ['CT-1008', 'C-01007', 'Contract', 3600000, 0, 150000, 1, '2026-01-01', '2027-12-31'],
        ];

        foreach ($contracts as [$id, $npk, $type, $salary, $daily, $allow, $ke, $start, $end]) {
            DB::connection('cii')->table('employees_contract')->insert([
                'id' => $id, 'npk' => $npk, 'type' => $type, 'salary' => $salary,
                'daily_salary' => $daily, 'allowance' => $allow, 'contract_ke' => $ke,
                'start_date' => $start, 'end_date' => $end, 'status_contract' => 'AKTIF',
            ]);
        }
    }

    private function makeBatch(float $percent = 2.7, float $umr = 2100000, int $roundTo = 100): WageRevisionBatch
    {
        $batch = WageRevisionBatch::create([
            'year' => 2027, 'umr_new' => $umr, 'effective_date' => '2027-01-01',
            'status' => WageRevisionBatch::STATUS_DRAFT, 'created_by' => 1,
        ]);

        $batch->rules()->createMany([
            ['segment' => WageRevisionItem::SEGMENT_NON_STAFF, 'mode' => WageRevisionRule::MODE_PERCENT,
             'percent' => $percent, 'round_to' => $roundTo, 'warn_below_umr' => true],
            ['segment' => WageRevisionItem::SEGMENT_STAFF_EXPAT, 'mode' => WageRevisionRule::MODE_NOMINAL,
             'percent' => null, 'round_to' => 100, 'warn_below_umr' => true],
        ]);

        return $batch->fresh();
    }

    private function builder(): WageRevisionBuilder
    {
        return app(WageRevisionBuilder::class);
    }

    private function applier(): WageRevisionApplier
    {
        return app(WageRevisionApplier::class);
    }

    private function contract(string $id): object
    {
        return DB::connection('cii')->table('employees_contract')->where('id', $id)->first();
    }

    // ------------------------------------------------------------------ T4: hitung non-staff

    public function test_non_staff_generated_with_one_uniform_percent(): void
    {
        $batch = $this->makeBatch();
        $stats = $this->builder()->buildNonStaffItems($batch, null);

        $this->assertSame(4, $stats['created'], 'empat karyawan non-staff aktif harus jadi item');
        $this->assertSame(0, $stats['invalid']);

        // Kontrak ke-2 yang dipakai (4500000 x 1.027 = 4621500)
        $item = $batch->items()->where('npk', 'C-01001')->first();
        $this->assertSame('valid', $item->status);
        $this->assertSame('CT-1002', $item->contract_id);
        $this->assertEqualsWithDelta(4621500, (float) $item->new_salary, 0.01);
        $this->assertEqualsWithDelta(121500, (float) $item->delta_monthly, 0.01);
        $this->assertEqualsWithDelta(200000, (float) $item->new_allowance, 0.01);
        $this->assertFalse((bool) $item->warn_below_umr);
    }

    public function test_percent_lands_on_daily_salary_for_daily_contract(): void
    {
        $batch = $this->makeBatch();
        $this->builder()->buildNonStaffItems($batch, null);

        // 180000 x 1.027 = 184860 -> bulat 100 -> 184900
        $item = $batch->items()->where('npk', 'C-01002')->first();
        $this->assertEqualsWithDelta(184900, (float) $item->new_daily_salary, 0.01);
        $this->assertEqualsWithDelta(0, (float) $item->new_salary, 0.01);
        // Januari 2027 = 31 hari: 184900 x 31 - 180000 x 31
        $this->assertEqualsWithDelta(151900, (float) $item->delta_monthly, 0.01);
    }

    public function test_below_umr_only_warns_without_flooring(): void
    {
        $batch = $this->makeBatch(2.7, 2100000);
        $this->builder()->buildNonStaffItems($batch, null);

        $item = $batch->items()->where('npk', 'C-01005')->first();
        // 2000000 x 1.027 = 2054000, tetap di bawah UMR 2100000 tapi TIDAK dinaikkan ke UMR
        $this->assertEqualsWithDelta(2054000, (float) $item->new_salary, 0.01);
        $this->assertTrue((bool) $item->warn_below_umr, 'gaji di bawah UMR harus diberi peringatan');
        $this->assertSame('valid', $item->status, 'peringatan tidak boleh menolak baris');
    }

    public function test_crossing_overtime_threshold_is_flagged(): void
    {
        $batch = $this->makeBatch();
        $this->builder()->buildNonStaffItems($batch, null);

        // 3600000 + 150000 = 3750000 (< 3.8jt) -> 3697200 + 150000 = 3847200 (>= 3.8jt)
        $item = $batch->items()->where('npk', 'C-01007')->first();
        $this->assertEqualsWithDelta(3697200, (float) $item->new_salary, 0.01);
        $this->assertTrue((bool) $item->warn_overtime_threshold);
    }

    public function test_resigned_and_placeholder_employees_are_excluded(): void
    {
        $batch = $this->makeBatch();
        $this->builder()->buildNonStaffItems($batch, null);

        $npks = $batch->items()->pluck('npk')->all();
        $this->assertNotContains('C-01006', $npks, 'karyawan yang sudah keluar tidak boleh diproses');
        $this->assertNotContains('C-00017', $npks, 'NPK placeholder harus dikecualikan');
    }

    public function test_payroll_role_scope_limits_generated_items(): void
    {
        $batch = $this->makeBatch();
        // Payroll_SEWING hanya melihat non-staff dengan IS_SEWING = 0 (dept 1)
        $this->builder()->buildNonStaffItems($batch, 'Payroll_SEWING');

        $npks = $batch->items()->pluck('npk')->sort()->values()->all();
        $this->assertSame(['C-01002', 'C-01005', 'C-01007'], $npks);
    }

    public function test_regenerate_keeps_manual_items(): void
    {
        $batch = $this->makeBatch();
        $this->builder()->buildNonStaffItems($batch, null);
        $this->builder()->saveStaffItem($batch, 'C-01003', 8350000, 'target', 'manual');
        $this->builder()->saveStaffItem($batch, 'C-01004', 250000, 'delta', 'manual');

        $this->builder()->clearGeneratedItems($batch);
        $stats = $this->builder()->buildNonStaffItems($batch, null);

        $this->assertSame(4, $stats['created']);
        $this->assertEqualsWithDelta(8350000, (float) $batch->items()->where('npk', 'C-01003')->value('new_salary'), 0.01);
        $this->assertEqualsWithDelta(15250000, (float) $batch->items()->where('npk', 'C-01004')->value('new_salary'), 0.01);
    }

    // ------------------------------------------------------------------ T5: nominal staff/expat

    public function test_staff_target_and_delta_modes(): void
    {
        $batch = $this->makeBatch();

        $target = $this->builder()->saveStaffItem($batch, 'C-01003', 8350000, 'target', 'manual');
        $this->assertSame('valid', $target->status);
        $this->assertEqualsWithDelta(8350000, (float) $target->new_salary, 0.01);
        $this->assertEqualsWithDelta(350000, (float) $target->delta_monthly, 0.01);
        $this->assertEqualsWithDelta(500000, (float) $target->new_allowance, 0.01, 'allowance tidak ikut naik');

        $delta = $this->builder()->saveStaffItem($batch, 'C-01004', 250000, 'delta', 'manual');
        $this->assertSame('valid', $delta->status);
        $this->assertEqualsWithDelta(15250000, (float) $delta->new_salary, 0.01);
        $this->assertTrue((bool) $delta->is_expat);
    }

    public function test_staff_below_current_salary_is_rejected(): void
    {
        $batch = $this->makeBatch();

        $item = $this->builder()->saveStaffItem($batch, 'C-01003', 7000000, 'target', 'manual');
        $this->assertSame('invalid', $item->status);
        $this->assertStringContainsString('lebih kecil', (string) $item->message);
    }

    public function test_non_staff_npk_is_rejected_on_staff_form(): void
    {
        $batch = $this->makeBatch();

        $item = $this->builder()->saveStaffItem($batch, 'C-01001', 5000000, 'target', 'manual');
        $this->assertSame('invalid', $item->status);
        $this->assertStringContainsString('staff/expat', (string) $item->message);
    }

    // ------------------------------------------------------------------ T8: gate role

    public function test_segment_gate_matrix(): void
    {
        $this->assertTrue(WageRevisionAccess::canManageStaffSegment('Payroll_STAFF', false));
        $this->assertTrue(WageRevisionAccess::canManageStaffSegment('Payroll_ALL', false));
        $this->assertFalse(WageRevisionAccess::canManageStaffSegment('Payroll_NONSTAFF', false));
        $this->assertFalse(WageRevisionAccess::canManageStaffSegment(null, false), 'tanpa role payroll = tolak');

        $this->assertTrue(WageRevisionAccess::canManageNonStaffSegment('Payroll_SEWING', false));
        $this->assertFalse(WageRevisionAccess::canManageNonStaffSegment('Payroll_STAFF', false));

        $this->assertFalse(WageRevisionAccess::canApprove('Payroll_STAFF', false));
        $this->assertTrue(WageRevisionAccess::canApprove('Payroll_ALL', false));
        $this->assertTrue(WageRevisionAccess::canManageStaffSegment(null, true), 'Admin boleh');
    }

    // ------------------------------------------------------------------ T6: import

    public function test_import_reads_indonesian_numbers_and_reports_every_row(): void
    {
        $batch = $this->makeBatch();
        $csv = database_path('wage_test_import.csv');
        file_put_contents($csv, implode("\n", [
            'npk,nama,departemen,is_staff,is_expat,gaji_pokok_sekarang,gaji_pokok_baru,nominal_kenaikan,keterangan',
            'C-01003,STAF UJI,UTILITY,1,0,8000000,8.350.000,,kenaikan tahunan',
            'C-01004,EXPAT UJI,UTILITY,0,1,15000000,,250000,kenaikan tahunan',
            'C-01005,NON STAF,UTILITY,0,0,2000000,2054000,,tidak boleh masuk',
            'C-01003,STAF UJI,UTILITY,1,0,8000000,8350000,500000,tidak konsisten',
        ]));

        $import = new WageRevisionItemImport($batch, $this->builder());
        Excel::import($import, $csv);
        unlink($csv);

        $this->assertSame(2, $import->valid, 'dua baris valid (target dan delta)');
        $this->assertSame(2, $import->invalid, 'baris non-staff dan baris tidak konsisten ditolak');
        $this->assertEqualsWithDelta(8350000, (float) $batch->items()->where('npk', 'C-01003')->value('new_salary'), 0.01);
        $this->assertEqualsWithDelta(15250000, (float) $batch->items()->where('npk', 'C-01004')->value('new_salary'), 0.01);
        $this->assertSame('import', $batch->items()->where('npk', 'C-01004')->value('source'));
    }

    public function test_import_rejects_amount_that_lost_its_thousand_separator(): void
    {
        $batch = $this->makeBatch();
        $csv = database_path('wage_test_import_small.csv');
        file_put_contents($csv, implode("\n", [
            'npk,nama,departemen,is_staff,is_expat,gaji_pokok_sekarang,gaji_pokok_baru,nominal_kenaikan,keterangan',
            // Excel/CSV membaca "250.000" sebagai angka 250 -> harus ditolak, bukan disimpan
            'C-01004,EXPAT UJI,UTILITY,0,1,15000000,,250.000,pemisah ribuan salah',
        ]));

        $import = new WageRevisionItemImport($batch, $this->builder());
        Excel::import($import, $csv);
        unlink($csv);

        $this->assertSame(0, $import->valid);
        $this->assertSame(1, $import->invalid);
        $this->assertSame('invalid', $batch->items()->where('npk', 'C-01004')->value('status'));
        $this->assertStringContainsString('tidak wajar', (string) $batch->items()->where('npk', 'C-01004')->value('message'));
    }

    // ------------------------------------------------------------------ T11: apply

    public function test_apply_updates_contract_and_writes_ledger(): void
    {
        $batch = $this->makeBatch();
        $this->builder()->buildNonStaffItems($batch, null);
        $this->builder()->saveStaffItem($batch, 'C-01003', 8350000, 'target', 'manual');
        $batch->update(['status' => WageRevisionBatch::STATUS_APPROVED]);

        $result = $this->applier()->apply($batch, 1);
        $this->assertSame(5, $result['applied']);
        $this->assertSame(0, $result['failed']);
        $this->assertNull($result['error']);

        $this->assertEqualsWithDelta(4621500, (float) $this->contract('CT-1002')->salary, 0.01);
        $this->assertEqualsWithDelta(184900, (float) $this->contract('CT-1003')->daily_salary, 0.01);
        $this->assertEqualsWithDelta(8350000, (float) $this->contract('CT-1004')->salary, 0.01);
        // allowance tidak berubah
        $this->assertEqualsWithDelta(500000, (float) $this->contract('CT-1004')->allowance, 0.01);

        $this->assertSame(5, EmployeeWageRevision::where('batch_id', $batch->id)->count());
        $this->assertSame('percent', EmployeeWageRevision::where('npk', 'C-01001')->value('policy'));
        $this->assertSame('nominal', EmployeeWageRevision::where('npk', 'C-01003')->value('policy'));
        $this->assertSame(WageRevisionBatch::STATUS_APPLIED, $batch->fresh()->status);
        $this->assertSame(5, $batch->items()->where('status', WageRevisionItem::STATUS_APPLIED)->count());
    }

    public function test_apply_requires_approved_status(): void
    {
        $batch = $this->makeBatch();
        $this->builder()->buildNonStaffItems($batch, null);

        $result = $this->applier()->apply($batch, 1);
        $this->assertNotNull($result['error']);
        $this->assertSame(0, $result['applied']);
        $this->assertEqualsWithDelta(4500000, (float) $this->contract('CT-1002')->salary, 0.01);
    }

    public function test_apply_is_idempotent_and_never_duplicates_the_ledger(): void
    {
        $batch = $this->makeBatch();
        $this->builder()->buildNonStaffItems($batch, null);
        $batch->update(['status' => WageRevisionBatch::STATUS_APPROVED]);
        $this->applier()->apply($batch, 1);

        // Jalankan ulang: status applied -> ditolak lebih awal, ledger tidak bertambah
        $again = $this->applier()->apply($batch->fresh(), 1);
        $this->assertNotNull($again['error']);
        $this->assertSame(4, EmployeeWageRevision::where('batch_id', $batch->id)->count());

        // Jalankan ulang paksa (status applying + item valid lagi): kenaikan TIDAK boleh dobel
        $batch->update(['status' => WageRevisionBatch::STATUS_APPLYING]);
        $batch->items()->update(['status' => WageRevisionItem::STATUS_VALID]);
        $resume = $this->applier()->apply($batch->fresh(), 1);

        $this->assertSame(0, $resume['applied']);
        $this->assertSame(4, $resume['failed'], 'kontrak sudah bernilai baru, apply ulang harus ditolak');
        $this->assertEqualsWithDelta(4621500, (float) $this->contract('CT-1002')->salary, 0.01, 'gaji tidak boleh naik dua kali');
        $this->assertSame(4, EmployeeWageRevision::where('batch_id', $batch->id)->count());

        // Jalankan ulang setelah kontrak dikembalikan ke nilai lama (run terputus setelah ledger
        // ditulis tetapi sebelum kontrak diperbarui): ledger tidak boleh dobel.
        DB::connection('cii')->table('employees_contract')->where('id', 'CT-1002')->update(['salary' => 4500000]);
        $batch->items()->where('npk', 'C-01001')->update(['status' => WageRevisionItem::STATUS_VALID]);
        $rerun = $this->applier()->apply($batch->fresh(), 1);

        $this->assertSame(1, $rerun['applied']);
        $this->assertEqualsWithDelta(4621500, (float) $this->contract('CT-1002')->salary, 0.01);
        $this->assertSame(4, EmployeeWageRevision::where('batch_id', $batch->id)->count(), 'baris ledger tetap satu per karyawan');
    }

    public function test_apply_refuses_when_contract_changed_after_simulation(): void
    {
        $batch = $this->makeBatch();
        $this->builder()->buildNonStaffItems($batch, null);
        $batch->update(['status' => WageRevisionBatch::STATUS_APPROVED]);

        // HRD mengubah kontrak setelah item dihitung
        DB::connection('cii')->table('employees_contract')->where('id', 'CT-1002')->update(['salary' => 4600000]);

        $result = $this->applier()->apply($batch, 1);
        $this->assertSame(1, $result['failed']);
        $this->assertEqualsWithDelta(4600000, (float) $this->contract('CT-1002')->salary, 0.01, 'nilai baru tidak boleh menimpa perubahan manual');
        $this->assertSame('failed', $batch->items()->where('npk', 'C-01001')->value('status'));
        $this->assertStringContainsString('berubah sejak simulasi', (string) $batch->items()->where('npk', 'C-01001')->value('message'));
        $this->assertSame(3, EmployeeWageRevision::where('batch_id', $batch->id)->count());
    }

    public function test_rollback_restores_previous_values(): void
    {
        $batch = $this->makeBatch();
        $this->builder()->buildNonStaffItems($batch, null);
        $this->builder()->saveStaffItem($batch, 'C-01003', 8350000, 'target', 'manual');
        $batch->update(['status' => WageRevisionBatch::STATUS_APPROVED]);
        $this->applier()->apply($batch, 1);

        $result = $this->applier()->rollback($batch->fresh(), 1);
        $this->assertSame(5, $result['restored']);
        $this->assertSame(0, $result['failed']);

        $this->assertEqualsWithDelta(4500000, (float) $this->contract('CT-1002')->salary, 0.01);
        $this->assertEqualsWithDelta(180000, (float) $this->contract('CT-1003')->daily_salary, 0.01);
        $this->assertEqualsWithDelta(8000000, (float) $this->contract('CT-1004')->salary, 0.01);
        $this->assertSame(WageRevisionBatch::STATUS_ROLLED_BACK, $batch->fresh()->status);
        $this->assertSame(5, $batch->items()->where('status', WageRevisionItem::STATUS_ROLLED_BACK)->count());
    }

    public function test_payroll_run_guard_detects_period_containing_effective_date(): void
    {
        Schema::create('payroll_periods', function (Blueprint $t) {
            $t->id();
            $t->date('start_date');
            $t->date('end_date');
        });
        Schema::create('payroll_runs', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('period_id');
        });

        DB::table('payroll_periods')->insert(['id' => 9, 'start_date' => '2027-01-01', 'end_date' => '2027-01-31']);
        DB::table('payroll_runs')->insert(['id' => 1, 'period_id' => 9]);

        $this->assertTrue($this->applier()->payrollRunExistsFor('2027-01-01'));
        $this->assertFalse($this->applier()->payrollRunExistsFor('2028-01-01'));
    }

    // ------------------------------------------------------------------ Export

    public function test_result_export_contains_every_item(): void
    {
        $batch = $this->makeBatch();
        $this->builder()->buildNonStaffItems($batch, null);

        $rows = (new WageRevisionResultExport($batch))->array();
        $this->assertCount(4, $rows);
        $this->assertCount(14, (new WageRevisionResultExport($batch))->headings());
        $this->assertSame('C-01001', $rows[0][0]);
    }

    // ------------------------------------------------------------------ Buka kunci (admin)

    public function test_unlock_returns_rolled_back_batch_to_simulation(): void
    {
        $batch = $this->makeBatch();
        $this->builder()->buildNonStaffItems($batch, null);
        $this->builder()->saveStaffItem($batch, 'C-01003', 8350000, 'target', 'manual');
        $batch->update(['status' => WageRevisionBatch::STATUS_APPROVED]);
        $this->applier()->apply($batch, 1);
        $this->applier()->rollback($batch->fresh(), 1);

        $this->assertSame(5, EmployeeWageRevision::where('batch_id', $batch->id)->count());

        $result = $this->applier()->unlock($batch->fresh());

        $this->assertNull($result['error']);
        $this->assertSame(5, $result['restored_items']);
        $this->assertSame(5, $result['cleared_ledger']);

        $fresh = $batch->fresh();
        $this->assertSame(WageRevisionBatch::STATUS_SIMULATED, $fresh->status, 'batch harus kembali ke status simulasi');
        $this->assertNull($fresh->applied_at);
        $this->assertSame(0, EmployeeWageRevision::where('batch_id', $batch->id)->count(), 'ledger lama dibersihkan');
        $this->assertSame(5, $batch->items()->where('status', WageRevisionItem::STATUS_VALID)->count());
        $this->assertEqualsWithDelta(4500000, (float) $this->contract('CT-1002')->salary, 0.01, 'unlock tidak boleh mengubah kontrak');

        // Setelah dibuka kuncinya, batch bisa disetujui dan diterapkan lagi tanpa ledger dobel.
        $fresh->update(['status' => WageRevisionBatch::STATUS_APPROVED]);
        $again = $this->applier()->apply($fresh->fresh(), 1);

        $this->assertSame(5, $again['applied']);
        $this->assertEqualsWithDelta(4621500, (float) $this->contract('CT-1002')->salary, 0.01);
        $this->assertSame(5, EmployeeWageRevision::where('batch_id', $batch->id)->count());
    }

    public function test_unlock_refuses_batch_that_is_not_rolled_back(): void
    {
        $batch = $this->makeBatch();
        $this->builder()->buildNonStaffItems($batch, null);

        $result = $this->applier()->unlock($batch->fresh());

        $this->assertNotNull($result['error']);
        $this->assertSame(0, $result['restored_items']);
        $this->assertSame(0, $result['cleared_ledger']);
        $this->assertSame(WageRevisionBatch::STATUS_DRAFT, $batch->fresh()->status);
    }

    // ------------------------------------------------------------------ Batas parameter SQL Server

    public function test_contracts_for_chunks_npk_so_sql_server_parameter_limit_is_never_hit(): void
    {
        // 2.345 NPK dalam satu whereIn = 2.345 parameter, sedangkan SQL Server menolak di atas 2.100.
        // Dengan NPK_CHUNK = 1000, permintaan harus dipecah menjadi 3 query (1000 + 1000 + 345).
        $npks = [];
        $rows = [];

        for ($i = 1; $i <= 2345; $i++) {
            $npk    = 'C-8' . str_pad((string) $i, 4, '0', STR_PAD_LEFT);
            $npks[] = $npk;
            $rows[] = [
                'id' => 'CH-' . $i, 'npk' => $npk, 'type' => 'Contract', 'salary' => 4000000,
                'daily_salary' => 0, 'allowance' => 0, 'pph21' => 0, 'contract_ke' => 1,
                'start_date' => '2026-01-01', 'end_date' => '2027-12-31', 'status_contract' => 'AKTIF',
            ];
        }

        foreach (array_chunk($rows, 500) as $chunk) {
            DB::connection('cii')->table('employees_contract')->insert($chunk);
        }

        $bindingCounts = [];
        DB::connection('cii')->listen(function ($query) use (&$bindingCounts) {
            $bindingCounts[] = count($query->bindings);
        });

        $contracts = $this->builder()->contractsFor($npks, '2027-01-01');

        $this->assertCount(2345, $contracts, 'setiap NPK harus mendapat kontraknya');
        $this->assertCount(3, $bindingCounts, 'NPK harus dipecah menjadi 3 query');
        $this->assertLessThan(2100, max($bindingCounts), 'tiap query wajib di bawah batas 2.100 parameter');
        // 1000 NPK + 2 tanggal, 1000 NPK + 2 tanggal, 345 NPK + 2 tanggal.
        $this->assertSame([1002, 1002, 347], $bindingCounts);
    }
}
