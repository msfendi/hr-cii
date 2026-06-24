<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use App\Exports\OvertimeCalendarExport;
use App\Exports\OvertimeCalendarTemplateExport;
use App\Exports\OvertimeTemplateExport;
use App\Models\Overtime;
use App\Imports\OvertimeImport;
use App\Models\PKWT;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Maatwebsite\Excel\Facades\Excel;

class OvertimeController extends Controller
{
    public function index()
    {
        $departments = Overtime::select('BAGIAN')->distinct()->get();

        return view('overtime.index', compact('departments'));
    }

    public function calendarOvertime()
    {
        return view('overtime.calendar');
    }

    public function downloadTemplateOvertime(Request $request)
    {
        $date = $request->input('date');
        $type = $request->input('type', 'sewing');

        if (!$date) {
            return redirect()->back()->with('error', 'Silahkan pilih tanggal.');
        }

        if (!in_array($type, ['sewing', 'non_sewing', 'staff', 'all'])) {
            return redirect()->back()->with('error', 'Silahkan pilih tipe template.');
        }

        $filename = 'template_overtime_' . $type . '_' . $date . '.xlsx';

        return Excel::download(new OvertimeTemplateExport($date, $type), $filename);
    }

    public function importOvertime(Request $request)
    {
        try {
            $request->validate([
                'file' => 'required|mimes:xlsx,xls',
                'dept_group' => 'required|in:sewing,non_sewing,staff',
                'month' => 'required',
            ]);

            $file = $request->file('file');
            $deptGroup = $request->input('dept_group');
            $month = $request->input('month');

            Excel::import(new OvertimeImport($deptGroup, $month), $file);

            // // INSERT UNTUK HARI HARI SEBELUM KARYAWAN BARU MASUK
            // $newEmployee = PKWT::whereMonth('TMK', Carbon::now()->month)->whereYear('TMK', Carbon::now()->year)->get();

            // foreach ($newEmployee as $employee) {
            //     $tmk = Carbon::parse($employee->TMK);
            //     for ($i = 1; $i < $tmk->day; $i++) {
            //         $loopDate = Carbon::create($tmk->year, $tmk->month, $i);
            //         Overtime::firstOrCreate(
            //             [
            //                 'NPK'          => $employee->NPK,
            //                 'OVERTIME_DATE' => $loopDate->format('Y-m-d'),
            //             ],
            //             [
            //                 'NAMA_KARYAWAN'     => $employee->NAMA_KARYAWAN,
            //                 'BAGIAN'            => $employee->BAGIAN,
            //                 'JUMLAH_JAM_LEMBUR' => 'BR',
            //                 'DAY'               => $loopDate->translatedFormat('l'),
            //                 'DEPT_GROUP'        => $deptGroup,
            //             ]
            //         );
            //     }
            // }

            if ($request->ajax() || $request->wantsJson()) {
                return response()->json(['status' => 'success', 'message' => 'Data overtime berhasil diimpor.']);
            }

            return redirect()->back()->with('success', 'Data overtime berhasil diimpor.');
        } catch (\Throwable $th) {
            if ($request->ajax() || $request->wantsJson()) {
                return response()->json(['status' => 'error', 'message' => 'Gagal mengimpor data overtime: ' . $th->getMessage()], 500);
            }
            return redirect()->back()->with('error', 'Gagal mengimpor data overtime: ' . $th->getMessage());
        }
    }

    public function getData(Request $request)
    {
        $date = $request->input('date');

        $query = Overtime::query();

        if ($date) {
            $query->whereDate('OVERTIME_DATE', $date);
        }

        if ($request->department_filter) {
            $query->where('DEPT_GROUP', $request->department_filter);
        }

        $overtimes = $query->orderBy('BAGIAN')->orderBy('NPK')->get();
        return response()->json(['data' => $overtimes]);
    }

    public function update(Request $request, $id)
    {
        try {
            // $request->validate([
            //     'jumlah_jam_lembur' => 'required|numeric',
            // ]);

            $overtime = Overtime::findOrFail($id);
            $overtime->update([
                'JUMLAH_JAM_LEMBUR' => $request->jumlah_jam_lembur,
            ]);

            return response()->json(['status' => 'success', 'message' => 'Data overtime berhasil diperbarui.']);
        } catch (\Exception $e) {
            return response()->json(['status' => 'error', 'message' => 'Gagal memperbarui data: ' . $e->getMessage()], 500);
        }
    }

    public function destroy(Request $request, $id)
    {
        try {
            $overtime = Overtime::findOrFail($id);
            $overtime->delete();

            return response()->json(['status' => 'success', 'message' => 'Data overtime berhasil dihapus.']);
        } catch (\Exception $e) {
            return response()->json(['status' => 'error', 'message' => 'Gagal menghapus data: ' . $e->getMessage()], 500);
        }
    }

    public function destroyAll(Request $request)
    {
        try {
            $date = $request->input('date');

            if (!$date) {
                return response()->json(['status' => 'error', 'message' => 'Tanggal tidak valid.'], 400);
            }

            $count = Overtime::whereDate('OVERTIME_DATE', $date)->delete();

            return response()->json(['status' => 'success', 'message' => $count . ' data overtime berhasil dihapus.']);
        } catch (\Exception $e) {
            return response()->json(['status' => 'error', 'message' => 'Gagal menghapus data: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Menampilkan data lembur dalam format kalender per bulan.
     * Data di-pivot menjadi satu row per karyawan, kolom = tanggal.
     * Termasuk perhitungan mingguan dan summary (kehadiran, lembur resmi, lembur khusus, dll).
     */
    public function calendarDisplay(Request $request)
    {
        $bulan       = $request->input('month', date('Y-m'));
        $duration    = $request->input('duration');
        $tanggalAwal = Carbon::parse($bulan)->startOfMonth();
        $tanggalAkhir = $tanggalAwal->copy()->endOfMonth();
        $jumlahHari  = $tanggalAkhir->day;

        // ▸ LANGKAH 1: Kelompokkan tanggal ke dalam minggu sesuai kalender
        //   Menggunakan Carbon startOfWeek(SUNDAY)
        $grupMinggu = [];
        for ($hari = 1; $hari <= $jumlahHari; $hari++) {
            $tanggal      = Carbon::create($tanggalAwal->year, $tanggalAwal->month, $hari);
            $awalMinggu   = $tanggal->copy()->startOfWeek(Carbon::SUNDAY)->format('Y-m-d');
            $grupMinggu[$awalMinggu][] = $hari;
        }
        ksort($grupMinggu);

        // Buat array range minggu dan metadata minggu untuk frontend
        $rangeMinggu = [];
        $metaMinggu  = [];
        $urutanMinggu = 0;
        foreach ($grupMinggu as $awalMinggu => $daftarHari) {
            $urutanMinggu++;
            $rangeMinggu[] = ['days' => $daftarHari];

            // Metadata hari (day_of_week) untuk highlight weekend di frontend
            $daysWithMeta = [];
            foreach ($daftarHari as $hari) {
                $tglObj = Carbon::create($tanggalAwal->year, $tanggalAwal->month, $hari);
                $daysWithMeta[] = [
                    'day'         => $hari,
                    'day_of_week' => $tglObj->dayOfWeek, // 0=Sunday, 6=Saturday
                ];
            }

            $metaMinggu[]  = [
                'label'     => 'Week ' . $urutanMinggu,
                'key'       => 'week_' . $urutanMinggu . '_sum',
                'days'      => $daftarHari,
                'days_meta' => $daysWithMeta,
            ];
        }

        // ▸ LANGKAH 2: Ambil data lembur dari database
        $deptGroup   = $request->input('dept_group');
        $queryLembur = Overtime::whereBetween('OVERTIME_DATE', [$tanggalAwal->format('Y-m-d'), $tanggalAkhir->format('Y-m-d')]);

        if ($deptGroup && $deptGroup !== 'all') {
            $queryLembur->where('DEPT_GROUP', $deptGroup);
        }

        $dataLembur = $queryLembur->get();

        // Filter berdasarkan durasi jam lembur (dropdown single value)
        if ($duration) {
            $duration = (int) $duration;
            $npkCocok = [];
            foreach ($dataLembur as $r) {
                if (is_numeric($r->JUMLAH_JAM_LEMBUR) && (int)$r->JUMLAH_JAM_LEMBUR === $duration) {
                    $npkCocok[$r->NPK] = true;
                }
            }
            $dataLembur = $dataLembur->whereIn('NPK', array_keys($npkCocok));
        }

        // ▸ LANGKAH 3: Ambil data hari libur nasional (cache 24 jam)
        $hariLibur = Cache::remember('holidays_calendar', 86400, function () {
            try {
                $json = file_get_contents(storage_path('app/calendar.json'));
                return json_decode($json, true) ?? [];
            } catch (\Exception $e) {
                return [];
            }
        });

        // ▸ PRE-COMPUTE: Setup cache meta per tanggal untuk menghindari loop parsing DateTime
        $dateMeta = [];
        $prefixBulan = $tanggalAwal->format('Y-m');
        $holidaysThisMonth = [];

        for ($d = 1; $d <= $jumlahHari; $d++) {
            $tglObj = Carbon::create($tanggalAwal->year, $tanggalAwal->month, $d);
            $dateStr = $tglObj->format('Y-m-d');
            $isWeekend = $tglObj->isWeekend();

            $holidayData = $hariLibur[$dateStr] ?? null;
            $isHoliday = false;
            if ($holidayData && ($holidayData['holiday'] ?? false) === true) {
                $summary = $holidayData['summary'] ?? [];
                if (!str_contains(implode(' ', (array) $summary), 'Cuti')) {
                    $isHoliday = true;
                    $holidaysThisMonth[$d] = $summary;
                }
            }

            $dateMeta[$dateStr] = [
                'is_weekend' => $isWeekend,
                'is_holiday' => $isHoliday,
                'hari_kerja' => !$isWeekend,
            ];
        }

        // Pre-compute days untuk perhitungan '_sum' per minggu
        $validDaysPerWeek = [];
        foreach ($rangeMinggu as $idx => $minggu) {
            $validDays = [];
            foreach ($minggu['days'] as $hari) {
                $keyDate = $prefixBulan . '-' . str_pad($hari, 2, '0', STR_PAD_LEFT);
                if (isset($dateMeta[$keyDate]) && !$dateMeta[$keyDate]['is_weekend'] && !$dateMeta[$keyDate]['is_holiday']) {
                    $validDays[] = $keyDate;
                }
            }
            $validDaysPerWeek['week_' . ($idx + 1) . '_sum'] = $validDays;
        }

        // ▸ LANGKAH 4: Pivot data — satu row per karyawan (NPK)
        $hasilPivot = $dataLembur->groupBy('NPK')->map(function ($grupKaryawan) use ($dateMeta, $validDaysPerWeek) {

            $employee = $grupKaryawan->first();
            $row = [
                'NPK'            => $employee->NPK,
                'NAMA_KARYAWAN'  => $employee->NAMA_KARYAWAN,
                'BAGIAN'         => $employee->BAGIAN,
            ];

            $jumlahHariLembur = 0;
            $totalJamLebih    = 0;
            $jumlahHariLebih  = 0;
            $totalKehadiran   = 0;
            $lemburKhusus     = 0;
            $lemburKarakter   = [];

            // ── Kalkulasi hanya dalam SATU loop tiap karyawan untuk performance ──
            foreach ($grupKaryawan as $record) {
                $tglValue = $record->OVERTIME_DATE;
                $tglStr   = $tglValue instanceof \DateTimeInterface ? $tglValue->format('Y-m-d') : substr((string)$tglValue, 0, 10);

                $jamLembur = $record->JUMLAH_JAM_LEMBUR;
                $row[$tglStr] = $jamLembur;

                $meta      = $dateMeta[$tglStr] ?? ['is_weekend' => false, 'is_holiday' => false, 'hari_kerja' => true];
                $isWeekend = $meta['is_weekend'];
                $isHoliday = $meta['is_holiday'];
                $hariKerja = $meta['hari_kerja'];

                if (is_numeric($jamLembur)) {
                    $jamLemburFloat = (float) $jamLembur;

                    // Lembur Resmi
                    if ($hariKerja && !$isHoliday && $jamLemburFloat >= 1 && $jamLemburFloat <= 8) {
                        $jumlahHariLembur++;
                        if ($jamLemburFloat > 1) {
                            $totalJamLebih += $jamLemburFloat;
                            $jumlahHariLebih++;
                        }
                    }

                    // Hitung jika jam lembur 0.5
                    if ($jamLemburFloat == 0.5) {
                        $jumlahHariLembur = $jumlahHariLembur + 0.5;
                    }

                    // Lembur Khusus
                    if ( ($isWeekend || $isHoliday)) {
                        $lemburKhusus += $jamLemburFloat;
                    }

                    // Total Kehadiran
                    if ($hariKerja && !$isHoliday) {
                        $totalKehadiran++;
                    }
                } else {
                    // Total Kehadiran jika jam lembur string kosong atau null
                    if (($jamLembur === null || $jamLembur === '') && $hariKerja && !$isHoliday) {
                        $totalKehadiran++;
                    }

                    // Hitung Kode Karakter (CT, MA, dll)
                    $kode = (string) $jamLembur;
                    if ($kode !== '') {
                        $lemburKarakter[$kode] = ($lemburKarakter[$kode] ?? 0) + 1;
                    }
                }
            }

            foreach ($lemburKarakter as $kode => $count) {
                $row[$kode] = ($row[$kode] ?? 0) + $count;
            }

            $jamEkstra = $totalJamLebih - $jumlahHariLebih;

            // ── Hitung Total Lembur Per Minggu ──
            foreach ($validDaysPerWeek as $weekKey => $validDays) {
                $totalMinggu = 0;
                foreach ($validDays as $dateKey) {
                    if (isset($row[$dateKey]) && is_numeric($row[$dateKey])) {
                        $totalMinggu += (float) $row[$dateKey];
                    }
                }
                $row[$weekKey] = $totalMinggu;
            }

            // ── Isi kolom summary ──
            $row['total_kehadiran'] = $totalKehadiran;
            $row['1']               = $jumlahHariLembur;
            $row['2']               = $jamEkstra;
            $row['total']           = $jumlahHariLembur + $jamEkstra;
            $row['lembur_khusus']   = $lemburKhusus;

            return $row;
        })->sortBy('NPK')->sortBy('BAGIAN')->values();

        return response()->json([
            'data'     => $hasilPivot,
            'weeks'    => $metaMinggu,
            'holidays' => $holidaysThisMonth,
        ]);
    }

    public function exportCalendar(Request $request)
    {
        $date = $request->input('date');
        $type = $request->input('type', 'all');

        if (!$date) {
            return redirect()->back()->with('error', 'Silahkan pilih tanggal.');
        }

        if (!in_array($type, ['sewing', 'non_sewing', 'staff', 'all'])) {
            return redirect()->back()->with('error', 'Silahkan pilih tipe export.');
        }

        // Ambil bulan dari input date (bisa format Y-m-d atau Y-m)
        $month = Carbon::parse($date)->format('Y-m');

        $filename = 'overtime_calendar_' . $type . '_' . $month . '.xlsx';

        return Excel::download(
            new OvertimeCalendarExport($month, $type),
            $filename
        );
    }

    public function exportCalendarTemplate(Request $request)
    {
        $date = $request->input('date');
        $type = $request->input('type', 'all');

        if (!$date) {
            return redirect()->back()->with('error', 'Silahkan pilih bulan.');
        }

        if (!in_array($type, ['sewing', 'non_sewing', 'staff', 'all'])) {
            return redirect()->back()->with('error', 'Silahkan pilih tipe template.');
        }

        // Accept both Y-m-d and Y-m formats
        $month    = Carbon::parse($date)->format('Y-m');
        $filename = 'template_kalender_' . $type . '_' . $month . '.xlsx';

        return Excel::download(
            new OvertimeCalendarTemplateExport($month, $type),
            $filename
        );
    }
}
