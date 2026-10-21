<?php

namespace App\Services;

use App\Models\LeaveBalances;
use App\Models\LeaveRequest;
use App\Models\LeaveTypes;
use Carbon\Carbon;

/**
 * Service aturan pengajuan cuti.
 *
 * Satu sumber kebenaran untuk validasi pengajuan cuti — dipakai oleh
 * form web (PengajuanCutiController) maupun API (Api\LeaveController,
 * Api\LeaveRequestController).
 *
 * Aturan yang diterapkan:
 * 1. Mode tanggal:
 *    - Tanggal bebas (custom dates): Cuti Tahunan & semua cuti selain
 *      melahirkan/keguguran. Tanggal boleh dipilih satu per satu dan tidak
 *      harus berurutan.
 *    - Rentang tanggal (awal & akhir): Cuti Melahirkan & Cuti Keguguran
 *      (dihitung hari kalender).
 * 2. Jumlah hari per pengajuan:
 *    - Cuti Tahunan: mengikuti sisa saldo (bisa menjadi Hutang Cuti).
 *    - Cuti biasa: WAJIB persis sama dengan default_days jenis cuti tersebut.
 *    - Cuti Pribadi (Unpaid): tanpa batas.
 * 3. Rentang tanggal: semua tanggal yang dipilih harus berada dalam rentang
 *    30 hari kalender dari tanggal pertama (kecuali Cuti Pribadi/Unpaid).
 * 4. Batas jumlah pengajuan (seumur hidup): bila sudah tercapai, jenis cuti
 *    tersebut tidak boleh dipakai lagi.
 */
class LeaveRuleService
{
    public function __construct(private LeaveDayService $dayService)
    {
    }

    /**
     * Validasi satu baris pengajuan cuti.
     *
     * @param  array<int,string>  $customDates  tanggal bebas (Y-m-d) dari request
     * @param  string|null        $reqStart     tanggal mulai (Y-m-d) dari request
     * @param  string|null        $reqEnd       tanggal selesai (Y-m-d) dari request
     * @return array{
     *     ok: bool,
     *     error: string|null,
     *     mode: string,
     *     dates: array<int,string>,
     *     custom_dates: array<int,string>|null,
     *     total_days: int,
     *     start: Carbon|null,
     *     end: Carbon|null,
     *     is_negative_leave: bool
     * }
     */
    public function evaluate(
        LeaveTypes $type,
        string $npk,
        array $customDates = [],
        ?string $reqStart = null,
        ?string $reqEnd = null
    ): array {
        $customDates = $this->dayService->normalizeCustomDates($customDates);

        // ── Tentukan mode tanggal ──
        if ($type->usesCustomDates() && !empty($customDates)) {
            $mode      = 'custom';
            $dates     = $customDates;
            $totalDays = $this->dayService->countCustomDays($type, $customDates);
            $start     = Carbon::parse($customDates[0]);
            $end       = Carbon::parse(end($customDates));
        } else {
            if (empty($reqStart) || empty($reqEnd)) {
                return $this->fail($type, "Tanggal mulai & selesai wajib diisi untuk jenis cuti {$type->name}.");
            }

            $mode      = 'range';
            $start     = Carbon::parse($reqStart);
            $end       = Carbon::parse($reqEnd);

            if ($end->lt($start)) {
                return $this->fail($type, "Tanggal selesai tidak boleh sebelum tanggal mulai.");
            }

            $totalDays = $this->dayService->countDays($type, $start, $end);
            $dates     = [$start->format('Y-m-d'), $end->format('Y-m-d')];
        }

        if ($totalDays <= 0) {
            return $this->fail($type, "Tanggal yang dipilih tidak valid (tidak ada hari kerja).");
        }

        // ── Aturan 2a: jumlah hari WAJIB persis = default_days ──
        if ($type->requiresExactDays()) {
            $required = $type->maxDaysPerRequest();

            if ($totalDays !== $required) {
                return $this->fail(
                    $type,
                    "{$type->name} wajib diisi tepat {$required} hari kerja per pengajuan (dipilih: {$totalDays} hari)."
                );
            }
        }

        // ── Aturan 2b: mode rentang (melahirkan/keguguran) dibatasi maksimal
        //    jumlah hari kalender sesuai jatah jenis cuti ──
        if (!$type->usesCustomDates() && !$type->isUnlimited()) {
            $maxDays = $type->maxDaysPerRequest();

            if ($totalDays > $maxDays) {
                return $this->fail(
                    $type,
                    "Jumlah hari ({$totalDays} hari) melebihi batas maksimal {$maxDays} hari untuk jenis cuti {$type->name}."
                );
            }
        }

        // ── Aturan 3: rentang 30 hari dari tanggal pertama ──
        if ($type->requiresDateWindow()) {
            $window = $type->dateWindowDays();

            if ($mode === 'custom') {
                $outside = $this->dayService->datesOutsideWindow($customDates, $window);

                if (!empty($outside)) {
                    $first = Carbon::parse($customDates[0])->format('d M Y');
                    $last  = Carbon::parse(end($outside))->format('d M Y');

                    return $this->fail(
                        $type,
                        "Semua tanggal {$type->name} harus berada dalam rentang {$window} hari dari tanggal pertama ({$first}). Tanggal terakhir yang dipilih: {$last}."
                    );
                }
            } elseif ($end->diffInDays($start) > $window) {
                return $this->fail(
                    $type,
                    "Rentang tanggal {$type->name} maksimal {$window} hari dari tanggal pertama."
                );
            }
        }

        // ── Aturan 2c: Cuti Tahunan memakai saldo ──
        $isNegativeLeave = false;

        if ($type->usesBalance()) {
            $balance = LeaveBalances::where('NPK', $npk)
                ->where('leave_type_id', $type->id)
                ->where('year', $start->year)
                ->first();

            if (!$balance) {
                return $this->fail(
                    $type,
                    "Jatah cuti tahunan untuk tahun {$start->year} tidak ditemukan. Hubungi HR untuk informasi lebih lanjut."
                );
            }

            $isNegativeLeave = (int) $balance->remained_days <= 0;

            if (!$isNegativeLeave && (int) $balance->remained_days < $totalDays) {
                return $this->fail(
                    $type,
                    "Jumlah Hari Cuti ({$totalDays} hari) Melebihi Sisa Jatah Cuti ({$balance->remained_days} hari)."
                );
            }
        }

        // ── Aturan 4: batas jumlah pengajuan (seumur hidup) ──
        $quota = $this->quota($type, $npk);

        if ($quota['exhausted']) {
            return $this->fail(
                $type,
                "Batas pengajuan {$type->name} sudah tercapai (maksimal {$quota['max']} pengajuan). Jenis cuti ini tidak dapat digunakan lagi."
            );
        }

        return [
            'ok'               => true,
            'error'            => null,
            'mode'             => $mode,
            'dates'            => $dates,
            'custom_dates'     => $mode === 'custom' ? $customDates : null,
            'total_days'       => $totalDays,
            'start'            => $start,
            'end'              => $end,
            'is_negative_leave' => $isNegativeLeave,
        ];
    }

    /**
     * Kuota pengajuan untuk satu jenis cuti (seumur hidup).
     *
     * @return array{max: int|null, used: int, remaining: int|null, exhausted: bool}
     */
    public function quota(LeaveTypes $type, string $npk): array
    {
        $max = $type->maxRequests();

        if ($max === null) {
            return ['max' => null, 'used' => 0, 'remaining' => null, 'exhausted' => false];
        }

        $used = $this->countUsedRequests($type, $npk);

        return [
            'max'       => $max,
            'used'      => $used,
            'remaining' => max(0, $max - $used),
            'exhausted' => $used >= $max,
        ];
    }

    /**
     * Jumlah pengajuan yang sudah memakai kuota untuk satu jenis cuti.
     *
     * Pengajuan yang ditolak (rejected) tidak dihitung — kuota hanya dipakai
     * oleh pengajuan yang masih berjalan (pending) atau sudah disetujui.
     */
    public function countUsedRequests(LeaveTypes $type, string $npk): int
    {
        return (int) LeaveRequest::where('NPK', $npk)
            ->where('leave_type_id', $type->id)
            ->where('status', '!=', 'rejected')
            ->where(function ($q) {
                $q->whereNull('void')->orWhere('void', '!=', 'true');
            })
            ->distinct()
            ->count('token');
    }

    /**
     * @return array<string,mixed>
     */
    private function fail(LeaveTypes $type, string $message): array
    {
        return [
            'ok'                => false,
            'error'             => $message,
            'mode'              => '',
            'dates'             => [],
            'custom_dates'      => null,
            'total_days'        => 0,
            'start'             => null,
            'end'               => null,
            'is_negative_leave' => false,
        ];
    }
}
