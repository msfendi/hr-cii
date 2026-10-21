<?php

namespace App\Services;

use App\Models\Holiday;
use App\Models\LeaveTypes;
use Carbon\Carbon;

/**
 * Service perhitungan hari cuti.
 *
 * Aturan:
 * - Jenis cuti non-kalender (default): hanya menghitung hari kerja,
 *   melewati akhir pekan & hari libur.
 * - Jenis cuti kalender (mis. melahirkan & keguguran): menghitung seluruh
 *   hari kalender termasuk akhir pekan & hari libur.
 */
class LeaveDayService
{
    /**
     * Ambil daftar tanggal hari libur pada rentang tertentu (format Y-m-d).
     */
    public function holidaysBetween(Carbon $start, Carbon $end): array
    {
        return Holiday::whereBetween('holiday_date', [
            $start->format('Y-m-d'),
            $end->format('Y-m-d'),
        ])->pluck('holiday_date')
            ->map(fn ($d) => Carbon::parse($d)->format('Y-m-d'))
            ->toArray();
    }

    /**
     * Hitung total hari cuti pada rentang [start, end] (keduanya inclusive)
     * sesuai jenis cuti.
     */
    public function countDays(LeaveTypes $type, Carbon $start, Carbon $end): int
    {
        if ($end->lt($start)) {
            return 0;
        }

        // Jenis cuti kalender: seluruh hari dihitung
        if ($type->countsCalendarDays()) {
            return (int) $start->diffInDays($end) + 1;
        }

        // Jenis cuti hari kerja: lewati akhir pekan & hari libur
        $holidays = $this->holidaysBetween($start, $end);
        $total = 0;
        for ($date = $start->copy(); $date->lte($end); $date->addDay()) {
            if ($date->isWeekend() || in_array($date->format('Y-m-d'), $holidays, true)) {
                continue;
            }
            $total++;
        }

        return $total;
    }

    /**
     * Normalisasi + urutkan daftar tanggal, sekaligus membuang tanggal kosong.
     *
     * @param  array<int,string>  $dates
     * @return array<int,string>
     */
    public function normalizeCustomDates(array $dates): array
    {
        $normalized = array_values(array_unique(array_filter(array_map(
            fn ($d) => Carbon::parse($d)->format('Y-m-d'),
            $dates
        ))));

        sort($normalized);

        return $normalized;
    }

    /**
     * Daftar tanggal kerja (bukan akhir pekan & bukan hari libur) dari daftar
     * tanggal yang dipilih.
     *
     * @param  array<int,string>  $dates  daftar tanggal format Y-m-d
     * @return array<int,string>
     */
    public function workingDates(array $dates): array
    {
        $normalized = $this->normalizeCustomDates($dates);

        if (empty($normalized)) {
            return [];
        }

        $holidays = $this->holidaysBetween(
            Carbon::parse($normalized[0]),
            Carbon::parse(end($normalized))
        );

        $working = [];
        foreach ($normalized as $date) {
            $carbon = Carbon::parse($date);
            if ($carbon->isWeekend() || in_array($date, $holidays, true)) {
                continue;
            }
            $working[] = $date;
        }

        return $working;
    }

    /**
     * Daftar tanggal kerja dalam rentang [start, end].
     *
     * @return array<int,string>
     */
    public function workingDatesBetween(Carbon $start, Carbon $end): array
    {
        if ($end->lt($start)) {
            return [];
        }

        $dates = [];
        for ($date = $start->copy(); $date->lte($end); $date->addDay()) {
            $dates[] = $date->format('Y-m-d');
        }

        return $this->workingDates($dates);
    }

    /**
     * Hitung total hari kerja dari daftar tanggal kustom (pilihan bebas).
     *
     * Tanggal duplikat diabaikan, weekend & hari libur tidak dihitung.
     *
     * @param  array<int,string>  $dates  daftar tanggal format Y-m-d
     */
    public function countCustomDays(LeaveTypes $type, array $dates): int
    {
        return count($this->workingDates($dates));
    }

    /**
     * Daftar tanggal yang berada DI LUAR jendela waktu (rentang $windowDays hari
     * kalender dari tanggal pertama).
     *
     * @param  array<int,string>  $dates
     * @return array<int,string>
     */
    public function datesOutsideWindow(array $dates, int $windowDays): array
    {
        if ($windowDays <= 0) {
            return [];
        }

        $normalized = $this->normalizeCustomDates($dates);

        if (empty($normalized)) {
            return [];
        }

        $limit = Carbon::parse($normalized[0])->addDays($windowDays);

        return array_values(array_filter(
            $normalized,
            fn ($date) => Carbon::parse($date)->gt($limit)
        ));
    }

    /**
     * Hitung tanggal selesai maksimum yang masih tercakup oleh sejumlah hari
     * (maxDays) mulai dari $start, sesuai jenis cuti.
     *
     * Mengembalikan null jika maxDays <= 0.
     */
    public function maxEndDate(LeaveTypes $type, Carbon $start, int $maxDays): ?Carbon
    {
        if ($maxDays <= 0) {
            return null;
        }

        // Jenis cuti kalender: mundur (maxDays - 1) hari dari tanggal mulai
        if ($type->countsCalendarDays()) {
            return $start->copy()->addDays($maxDays - 1);
        }

        // Ambil hari libur pada jendela yang cukup lebar (jumlah hari kerja <= 2x hari kalender)
        $windowEnd = $start->copy()->addDays(($maxDays * 2) + 60);
        $holidays = $this->holidaysBetween($start, $windowEnd);

        $count = 0;
        $cursor = $start->copy();
        $guard = 0;
        while ($count < $maxDays && $guard < ($maxDays * 3) + 90) {
            if (!$cursor->isWeekend() && !in_array($cursor->format('Y-m-d'), $holidays, true)) {
                $count++;
            }
            if ($count >= $maxDays) {
                break;
            }
            $cursor->addDay();
            $guard++;
        }

        return $cursor;
    }
}
