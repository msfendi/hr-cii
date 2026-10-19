<?php

namespace App\Services;

use App\Models\AuditTrail;
use App\Models\LeaveBalances;
use App\Models\LeaveTypes;
use App\Models\PKWT;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class LeaveBalanceGenerationService
{
    /**
     * Generate balance cuti untuk tahun tertentu.
     *
     * Aturan:
     * - Karyawan aktif: TKK kosong / NULL
     * - Masa kerja minimal 1 tahun pada 1 Januari tahun periode
     * - Leave Type harus aktif
     * - Gender:
     *   A = semua gender
     *   L = laki-laki
     *   P = perempuan
     * - Jika JK karyawan kosong, semua jenis cuti tetap digenerate.
     */
    public function generate(int $year): array
    {
        $leaveTypes = LeaveTypes::where('is_active', true)->get();

        if ($leaveTypes->isEmpty()) {
            return [
                'success' => false,
                'message' => 'No active Leave Types found',
            ];
        }

        // Ambil karyawan berdasarkan periode yang dipilih
        $employees = $this->getEligibleEmployees($year);

        $created = 0;
        $skipped = 0;
        $skippedGender = 0;

        DB::transaction(function () use (
            $employees,
            $leaveTypes,
            $year,
            &$created,
            &$skipped,
            &$skippedGender
        ) {
            foreach ($employees as $employee) {

                // Normalisasi JK
                $jk = !empty($employee->JK)
                    ? strtoupper(trim($employee->JK))
                    : null;

                foreach ($leaveTypes as $type) {

                    // A = semua gender
                    $genderType = !empty($type->gender_type)
                        ? strtoupper(trim($type->gender_type))
                        : 'A';

                    // Jika cuti khusus gender dan JK karyawan berbeda,
                    // jangan generate balance.
                    if (
                        $genderType !== 'A' &&
                        $jk !== null &&
                        $genderType !== $jk
                    ) {
                        $skippedGender++;
                        continue;
                    }

                    $balance = LeaveBalances::firstOrCreate(
                        [
                            'NPK'           => $employee->NPK,
                            'leave_type_id' => $type->id,
                            'year'          => $year,
                        ],
                        [
                            'remained_days' => $type->default_days,
                            'used_days'     => 0,
                        ]
                    );

                    if ($balance->wasRecentlyCreated) {
                        $created++;
                    } else {
                        $skipped++;
                    }
                }
            }
        });

        // Catat ke audit_trails jika berhasil generate
        try {
            AuditTrail::create([
                'user_id'        => Auth::id(),
                'user_name'      => Auth::check() ? (Auth::user()->name ?? null) : (app()->runningInConsole() ? 'SYSTEM (CLI)' : 'SYSTEM (Yearly Generate)'),
                'event'          => 'generate',
                'auditable_type' => LeaveBalances::class,
                'auditable_id'   => null,
                'old_values'     => null,
                'new_values'     => [
                    'action'          => 'generate_yearly_leave_balances',
                    'description'     => "Generate saldo cuti tahunan periode tahun $year",
                    'year'            => $year,
                    'employees_count' => $employees->count(),
                    'created_count'   => $created,
                    'skipped_count'   => $skipped,
                    'skipped_gender'  => $skippedGender,
                ],
                'url'            => app()->runningInConsole() ? null : request()->fullUrl(),
                'ip_address'     => app()->runningInConsole() ? null : request()->ip(),
                'user_agent'     => app()->runningInConsole() ? null : substr((string) request()->userAgent(), 0, 500),
            ]);
        } catch (\Throwable $e) {
            // Abaikan error audit trail agar tidak mengganggu transaksi utama
        }

        return [
            'success'         => true,
            'employees_count' => $employees->count(),
            'created'         => $created,
            'skipped'         => $skipped,
            'skipped_gender'  => $skippedGender,
        ];
    }

    /**
     * Generate leave balance untuk employee (semua jenis cuti aktif di leave_types KECUALI cuti tahunan).
     *
     * @param mixed $employee String NPK, object PKWT/Biodata, atau array data employee
     * @param string|null $jk Gender override ('L', 'P', atau 'A')
     * @param int|null $year Tahun saldo cuti (default tahun berjalan)
     * @return array
     */
    public function generateForEmployee($employee, ?string $jk = null, ?int $year = null): array
    {
        $npk = null;
        if (is_string($employee)) {
            $npk = strtoupper(trim($employee));
        } elseif (is_object($employee)) {
            $npk = strtoupper(trim($employee->NPK ?? $employee->npk ?? ''));
            if ($jk === null) {
                $jk = $employee->JK ?? $employee->jk ?? $employee->JENIS_KEL ?? $employee->jenis_kel ?? null;
            }
        } elseif (is_array($employee)) {
            $npk = strtoupper(trim($employee['NPK'] ?? $employee['npk'] ?? ''));
            if ($jk === null) {
                $jk = $employee['JK'] ?? $employee['jk'] ?? $employee['JENIS_KEL'] ?? $employee['jenis_kel'] ?? null;
            }
        }

        if (empty($npk)) {
            return [
                'success' => false,
                'message' => 'NPK tidak valid',
                'created' => 0,
                'skipped' => 0,
            ];
        }

        // Jika JK belum tersedia, cari dari PKWT atau BIODATA
        if (empty($jk)) {
            $found = PKWT::where('NPK', $npk)->select('JK')->first();
            if ($found && !empty($found->JK)) {
                $jk = $found->JK;
            } else {
                $foundBio = DB::connection('cii')->table('BIODATA')->where('NPK', $npk)->select('JENIS_KEL')->first();
                if ($foundBio && !empty($foundBio->JENIS_KEL)) {
                    $jk = $foundBio->JENIS_KEL;
                }
            }
        }

        // Normalisasi JK
        $jkNorm = null;
        if (!empty($jk)) {
            $clean = strtoupper(trim($jk));
            if ($clean === 'LAKI-LAKI' || $clean === 'L') {
                $jkNorm = 'L';
            } elseif ($clean === 'PEREMPUAN' || $clean === 'P') {
                $jkNorm = 'P';
            } else {
                $jkNorm = $clean;
            }
        }

        $year = $year ?? (int) date('Y');

        // Ambil semua leave types yang aktif KECUALI cuti tahunan
        $leaveTypes = LeaveTypes::where('is_active', true)->get()->filter(function ($type) {
            return !$this->isAnnualLeave($type);
        });

        if ($leaveTypes->isEmpty()) {
            return [
                'success' => true,
                'message' => 'Tidak ada Leave Types non-tahunan yang aktif',
                'created' => 0,
                'skipped' => 0,
            ];
        }

        $created = 0;
        $skipped = 0;
        $skippedGender = 0;

        foreach ($leaveTypes as $type) {
            $genderType = !empty($type->gender_type)
                ? strtoupper(trim($type->gender_type))
                : 'A';

            // Jika cuti khusus gender dan JK karyawan berbeda, jangan generate balance
            if ($genderType !== 'A' && $jkNorm !== null && $genderType !== $jkNorm) {
                $skippedGender++;
                continue;
            }

            $balance = LeaveBalances::firstOrCreate(
                [
                    'NPK'           => $npk,
                    'leave_type_id' => $type->id,
                    'year'          => $year,
                ],
                [
                    'remained_days' => $type->default_days,
                    'used_days'     => 0,
                ]
            );

            if ($balance->wasRecentlyCreated) {
                $created++;
            } else {
                $skipped++;
            }
        }

        // Catat ke audit_trails jika ada data saldo yang baru dibuat
        if ($created > 0) {
            try {
                AuditTrail::create([
                    'user_id'        => Auth::id(),
                    'user_name'      => Auth::check() ? (Auth::user()->name ?? null) : (app()->runningInConsole() ? 'SYSTEM (CLI)' : 'SYSTEM (Auto-Generate)'),
                    'event'          => 'generate',
                    'auditable_type' => LeaveBalances::class,
                    'auditable_id'   => null,
                    'old_values'     => null,
                    'new_values'     => [
                        'action'         => 'generate_leave_balances_employee',
                        'description'    => "Auto-generate leave balance non-tahunan untuk NPK $npk",
                        'npk'            => $npk,
                        'year'           => $year,
                        'created_count'  => $created,
                        'skipped_count'  => $skipped,
                        'skipped_gender' => $skippedGender,
                    ],
                    'url'            => app()->runningInConsole() ? null : request()->fullUrl(),
                    'ip_address'     => app()->runningInConsole() ? null : request()->ip(),
                    'user_agent'     => app()->runningInConsole() ? null : substr((string) request()->userAgent(), 0, 500),
                ]);
            } catch (\Throwable $e) {
                // Abaikan error audit trail
            }
        }

        return [
            'success'        => true,
            'npk'            => $npk,
            'year'           => $year,
            'created'        => $created,
            'skipped'        => $skipped,
            'skipped_gender' => $skippedGender,
        ];
    }

    /**
     * Cek apakah leave type merupakan Cuti Tahunan.
     */
    public function isAnnualLeave(LeaveTypes $type): bool
    {
        $code = strtolower(trim($type->code ?? ''));
        $name = strtolower(trim($type->name ?? ''));

        return $code === 'tahunan'
            || str_contains($code, 'tahunan')
            || str_contains($name, 'tahunan')
            || str_contains($code, 'annual')
            || str_contains($name, 'annual');
    }

    /**
     * Generate leave balance non-tahunan untuk seluruh karyawan aktif (TKK NULL).
     */
    public function generateNonAnnualForActiveEmployees(?int $year = null): array
    {
        $year = $year ?? (int) date('Y');

        $employees = PKWT::select('NPK', 'JK')
            ->whereNull('TKK')
            ->get();

        $totalCreated = 0;
        $totalSkipped = 0;
        $totalSkippedGender = 0;

        foreach ($employees as $emp) {
            $res = $this->generateForEmployee($emp->NPK, $emp->JK, $year);
            $totalCreated += $res['created'] ?? 0;
            $totalSkipped += $res['skipped'] ?? 0;
            $totalSkippedGender += $res['skipped_gender'] ?? 0;
        }

        // Catat summary ke audit_trails
        try {
            AuditTrail::create([
                'user_id'        => Auth::id(),
                'user_name'      => Auth::check() ? (Auth::user()->name ?? null) : (app()->runningInConsole() ? 'SYSTEM (CLI)' : 'SYSTEM (Batch Generate)'),
                'event'          => 'generate',
                'auditable_type' => LeaveBalances::class,
                'auditable_id'   => null,
                'old_values'     => null,
                'new_values'     => [
                    'action'          => 'generate_non_annual_all_active_employees',
                    'description'     => "Batch generate leave balance non-tahunan seluruh karyawan aktif tahun $year",
                    'year'            => $year,
                    'employees_count' => $employees->count(),
                    'created_count'   => $totalCreated,
                    'skipped_count'   => $totalSkipped,
                    'skipped_gender'  => $totalSkippedGender,
                ],
                'url'            => app()->runningInConsole() ? null : request()->fullUrl(),
                'ip_address'     => app()->runningInConsole() ? null : request()->ip(),
                'user_agent'     => app()->runningInConsole() ? null : substr((string) request()->userAgent(), 0, 500),
            ]);
        } catch (\Throwable $e) {
            // Abaikan error audit trail
        }

        return [
            'success'         => true,
            'employees_count' => $employees->count(),
            'created'         => $totalCreated,
            'skipped'         => $totalSkipped,
            'skipped_gender'  => $totalSkippedGender,
            'year'            => $year,
        ];
    }

    /**
     * Ambil karyawan yang eligible berdasarkan periode yang dipilih.
     *
     * Contoh:
     *
     * Year 2026:
     * TMK <= 2025-01-01
     *
     * Year 2027:
     * TMK <= 2026-01-01
     *
     * Year 2028:
     * TMK <= 2027-01-01
     */
    protected function getEligibleEmployees(int $year)
    {
        // Awal periode cuti
        $periodDate = Carbon::create($year, 10, 7);

        // Minimal sudah bekerja 1 tahun
        $minimumTmkDate = $periodDate->copy()->subYear();

        return DB::table('PKWT')
            ->select(
                'PKWT.NPK',
                'PKWT.TMK',
                'PKWT.TKK',
                'PKWT.JK'
            )
            ->leftJoin('BIODATA', 'PKWT.NPK', '=', 'BIODATA.NPK')
            ->whereNull('PKWT.TKK')
            ->whereNotNull('PKWT.TMK')
            ->where('PKWT.TMK', '<=', $minimumTmkDate->format('Y-m-d'))
            ->where('BIODATA.IS_STAFF', '1')
            ->get();
    }
}