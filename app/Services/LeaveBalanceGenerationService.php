<?php

namespace App\Services;

use App\Models\Biodata;
use App\Models\LeaveBalances;
use App\Models\LeaveTypes;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Menangani logika generate jatah cuti tahunan untuk seluruh karyawan tetap.
 * Dipisah dari controller agar bisa dites & dipakai ulang (mis. dari command/scheduler)
 * tanpa harus lewat HTTP request.
 */
class LeaveBalanceGenerationService
{
    /**
     * Generate balance cuti untuk tahun tertentu.
     *
     * Aturan gender:
     * - leave_types.gender_type bernilai 'L', 'P', atau 'A' (berlaku untuk semua).
     * - Jika gender_type spesifik ('L'/'P') dan tidak sama dengan JK karyawan (dari PKWT),
     *   maka balance untuk jenis cuti tersebut TIDAK DIGENERATE SAMA SEKALI (bukan dibuat 0).
     * - Jika JK karyawan tidak diketahui (null/kosong), semua jenis cuti tetap digenerate
     *   agar tidak ada karyawan yang kehilangan jatah cuti karena data tidak lengkap.
     *
     * @return array{success: bool, message?: string, employees_count?: int, created?: int, skipped?: int, skipped_gender?: int}
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

        $employees = $this->getEligibleEmployees();

        $created = 0;
        $skipped = 0;
        $skippedGender = 0;

        DB::transaction(function () use ($employees, $leaveTypes, $year, &$created, &$skipped, &$skippedGender) {
            foreach ($employees as $employee) {
                // Normalisasi JK dari PKWT: 'L' (Laki-laki) atau 'P' (Perempuan)
                $jk = $employee->JK ? strtoupper(trim($employee->JK)) : null;

                foreach ($leaveTypes as $type) {
                    $genderType = $type->gender_type ? strtoupper(trim($type->gender_type)) : 'A';

                    // Cuti khusus gender: hanya generate jika JK karyawan cocok dengan gender_type.
                    // 'A' = berlaku untuk semua gender.
                    if ($genderType !== 'A' && $jk !== null && $genderType !== $jk) {
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

                    $balance->wasRecentlyCreated ? $created++ : $skipped++;
                }
            }
        });

        return [
            'success'         => true,
            'employees_count' => $employees->count(),
            'created'         => $created,
            'skipped'         => $skipped,
            'skipped_gender'  => $skippedGender,
        ];
    }

    /**
     * Karyawan tetap = TKK kosong (belum berhenti) & masa kerja (TMK) >= 1 tahun.
     * Diambil via Eloquent dulu lalu difilter di PHP agar tidak terpengaruh
     * format kolom tanggal yang tidak konsisten di database sumber (cii).
     */
    protected function getEligibleEmployees(int $year)
    {
        // Awal periode cuti
        $periodDate = Carbon::create($year, 1, 1);

        // Minimal sudah bekerja 1 tahun
        $minimumTmkDate = $periodDate->copy()->subYear();

        return DB::table('PKWT')
            ->select(
                'PKWT.NPK',
                'PKWT.TMK',
                'PKWT.TKK',
                'PKWT.JK'
            )
            ->whereNull('PKWT.TKK')
            ->whereNotNull('PKWT.TMK')
            ->where('PKWT.TMK', '<=', $minimumTmkDate->format('Y-m-d'))
            ->get();
    }
}