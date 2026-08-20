<?php

namespace App\Services;

use App\Models\LeaveBalances;
use App\Models\LeaveTypes;
use Carbon\Carbon;
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

        return [
            'success'         => true,
            'employees_count' => $employees->count(),
            'created'         => $created,
            'skipped'         => $skipped,
            'skipped_gender'  => $skippedGender,
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