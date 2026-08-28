<?php

namespace App\Exports;

use Illuminate\Contracts\View\View;
use Maatwebsite\Excel\Concerns\FromView;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;

/**
 * Export BARU: laporan attendance bulanan / rentang tanggal custom,
 * difilter per departemen (atau semua departemen).
 *
 * Ini SEPENUHNYA terpisah dari App\Exports\Sheets\AttendanceFingerExport
 * (export harian yang sudah ada) — tidak menyentuh file/class itu sama
 * sekali, tidak dipakai bersama, dan bisa dihapus tanpa mempengaruhi
 * export harian.
 *
 * Memakai FromView supaya rowspan (identity kolom: No/NPK/Nama/Bagian)
 * pada tabel HTML (resources/views/attendance_finger/export_monthly.blade.php)
 * otomatis dikonversi jadi merged cell oleh Maatwebsite Excel / PhpSpreadsheet.
 */
class AttendanceMonthlyByDeptExport implements FromView, ShouldAutoSize
{
    /** @var array<int,string> */
    protected array $dates;

    /** @var array<int,array> */
    protected array $employees;

    protected string $deptLabel;
    protected string $periodLabel;

    public function __construct(array $dates, array $employees, string $deptLabel, string $periodLabel)
    {
        $this->dates       = $dates;
        $this->employees   = $employees;
        $this->deptLabel   = $deptLabel;
        $this->periodLabel = $periodLabel;
    }

    public function view(): View
    {
        return view('attendance_finger.export_monthly', [
            'dates'       => $this->dates,
            'employees'   => $this->employees,
            'deptLabel'   => $this->deptLabel,
            'periodLabel' => $this->periodLabel,
        ]);
    }
}