<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use App\Exports\OvertimeCalendarExport;
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
            ]);

            $file = $request->file('file');
            $deptGroup = $request->input('dept_group');

            Excel::import(new OvertimeImport($deptGroup), $file);

            // INSERT UNTUK HARI HARI SEBELUM KARYAWAN BARU MASUK
            $newEmployee = PKWT::whereMonth('TMK', Carbon::now()->month)->whereYear('TMK', Carbon::now()->year)->get();

            foreach ($newEmployee as $employee) {
                $tmk = Carbon::parse($employee->TMK);
                for ($i = 1; $i < $tmk->day; $i++) {
                    $loopDate = Carbon::create($tmk->year, $tmk->month, $i);
                    Overtime::firstOrCreate(
                        [
                            'NPK'          => $employee->NPK,
                            'OVERTIME_DATE' => $loopDate->format('Y-m-d'),
                        ],
                        [
                            'NAMA_KARYAWAN'     => $employee->NAMA_KARYAWAN,
                            'BAGIAN'            => $employee->BAGIAN,
                            'JUMLAH_JAM_LEMBUR' => 'BR',
                            'DAY'               => $loopDate->translatedFormat('l'),
                            'DEPT_GROUP'        => $deptGroup,
                        ]
                    );
                }
            }

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
        $tanggalAwal = \Carbon\Carbon::parse($bulan)->startOfMonth();
        $tanggalAkhir = \Carbon\Carbon::parse($bulan)->endOfMonth();
        $jumlahHari  = $tanggalAkhir->day;

        // ▸ LANGKAH 1: Kelompokkan tanggal ke dalam minggu sesuai kalender
        //   Menggunakan Carbon startOfWeek(SUNDAY)
        $grupMinggu = [];
        for ($hari = 1; $hari <= $jumlahHari; $hari++) {
            $tanggal      = \Carbon\Carbon::create($tanggalAwal->year, $tanggalAwal->month, $hari);
            $awalMinggu   = $tanggal->copy()->startOfWeek(\Carbon\Carbon::SUNDAY)->format('Y-m-d');
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
                $tglObj = \Carbon\Carbon::create($tanggalAwal->year, $tanggalAwal->month, $hari);
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
        $queryLembur = Overtime::whereBetween('OVERTIME_DATE', [$tanggalAwal, $tanggalAkhir]);

        if ($deptGroup && $deptGroup !== 'all') {
            $queryLembur->where('DEPT_GROUP', $deptGroup);
        }

        $dataLembur = $queryLembur->get();

        // Filter berdasarkan durasi jam lembur (dropdown single value)
        if ($duration) {
            $duration = (int) $duration;
            $npkCocok = $dataLembur->filter(function ($r) use ($duration) {
                return is_numeric($r->JUMLAH_JAM_LEMBUR)
                    && (int)$r->JUMLAH_JAM_LEMBUR === $duration;
            })->pluck('NPK')->unique();
            $dataLembur = $dataLembur->whereIn('NPK', $npkCocok);
        }

        // ▸ LANGKAH 3: Ambil data hari libur nasional (cache 24 jam)
        $hariLibur = Cache::remember('holidays_calendar', 86400, function () {
            try {
                $response = Http::get('https://raw.githubusercontent.com/guangrei/APIHariLibur_V2/main/calendar.json');
                return $response->json();
            } catch (\Exception $e) {
                return [];
            }
        });

        // ▸ LANGKAH 4: Pivot data — satu row per karyawan (NPK)
        $hasilPivot = $dataLembur->groupBy('NPK')->map(function ($grupKaryawan) use ($hariLibur, $rangeMinggu, $tanggalAwal) {

            $employee = $grupKaryawan->first();
            $row = [
                'NPK'            => $employee->NPK,
                'NAMA_KARYAWAN'  => $employee->NAMA_KARYAWAN,
                'BAGIAN'         => $employee->BAGIAN,
            ];

            // ── Isi kolom tanggal: mapping tanggal → jam lembur ──
            foreach ($grupKaryawan as $record) {
                $tgl = \Carbon\Carbon::parse($record->OVERTIME_DATE)->format('Y-m-d');
                $row[$tgl] = $record->JUMLAH_JAM_LEMBUR;
            }

            // ── Hitung Lembur Resmi ──
            // Syarat: hari kerja (bukan weekend), bukan hari libur, jam lembur antara 1-8
            $lemburResmi = $grupKaryawan->filter(function ($record) use ($hariLibur) {
                $tanggal     = \Carbon\Carbon::parse($record->OVERTIME_DATE);
                $hariKerja   = !$tanggal->isWeekend();
                $tglString   = $tanggal->format('Y-m-d');
                $holidayData = $hariLibur[$tglString] ?? null;
                $isHoliday   = ($holidayData['holiday'] ?? false) === true
                    && !str_contains(implode(' ', (array)($holidayData['summary'] ?? [])), 'Cuti');
                $jamLembur   = $record->JUMLAH_JAM_LEMBUR;

                return $hariKerja
                    && !$isHoliday
                    && is_numeric($jamLembur)
                    && $jamLembur >= 1
                    && $jamLembur <= 8;
            });

            // Kolom '1': Jumlah hari lembur resmi
            $jumlahHariLembur = $lemburResmi->count();

            // ── Hitung Jam Lembur Lebih (kolom '2') ──
            // Rumus: total jam yang > 1 dikurangi jumlah hari yang > 1
            // Contoh: karyawan lembur 3 jam dan 2 jam = (3+2) - 2 = 3 jam ekstra
            $jamLebihDariSatu   = $lemburResmi->filter(fn($r) => $r->JUMLAH_JAM_LEMBUR > 1);
            $totalJamLebih      = $jamLebihDariSatu->sum('JUMLAH_JAM_LEMBUR');
            $jumlahHariLebih    = $jamLebihDariSatu->count();
            $jamEkstra          = $totalJamLebih - $jumlahHariLebih;

            // ── Hitung Total Kehadiran ──
            // Syarat: hari kerja, bukan libur, dan nilai jam lembur numerik/null/kosong
            // (exclude kode karakter seperti CT, MA)
            $totalKehadiran = $grupKaryawan->filter(function ($record) use ($hariLibur) {
                $tanggal     = \Carbon\Carbon::parse($record->OVERTIME_DATE);
                $hariKerja   = !$tanggal->isWeekend();
                $tglString   = $tanggal->format('Y-m-d');
                $holidayData = $hariLibur[$tglString] ?? null;
                $isHoliday   = ($holidayData['holiday'] ?? false) === true
                    && !str_contains(implode(' ', (array)($holidayData['summary'] ?? [])), 'Cuti');
                $nilai       = $record->JUMLAH_JAM_LEMBUR;

                return $hariKerja
                    && !$isHoliday
                    && (is_numeric($nilai) || is_null($nilai) || $nilai === '');
            })->count();

            // ── Hitung Lembur Khusus ──
            // Syarat: jam lembur > 4 pada hari weekend atau hari libur nasional
            $lemburKhusus = $grupKaryawan->filter(function ($record) use ($hariLibur) {
                $nilai = $record->JUMLAH_JAM_LEMBUR;
                if (!is_numeric($nilai) || $nilai <= 4) {
                    return false;
                }

                $tanggal     = \Carbon\Carbon::parse($record->OVERTIME_DATE);
                $tglString   = $tanggal->format('Y-m-d');
                $isHoliday = isset($hariLibur[$tglString]) && $hariLibur[$tglString]['holiday'] === true;

                return $tanggal->isWeekend() || $isHoliday;
            })->sum('JUMLAH_JAM_LEMBUR');

            // ── Hitung Kode Karakter (CT, MA, dll) ──
            // Ambil record yang jam lemburnya bukan angka, lalu hitung per kode
            $lemburKarakter = $grupKaryawan
                ->filter(fn($r) => !is_numeric($r->JUMLAH_JAM_LEMBUR))
                ->groupBy('JUMLAH_JAM_LEMBUR');

            foreach ($lemburKarakter as $kode => $daftarRecord) {
                $row[$kode] = $daftarRecord->count();
            }

            // ── Hitung Total Lembur Per Minggu ──
            // Untuk menandai minggu yang melebihi 16 jam (highlight merah di frontend)
            $prefixBulan = $tanggalAwal->format('Y-m');
            foreach ($rangeMinggu as $idx => $minggu) {
                $totalMinggu = 0;
                foreach ($minggu['days'] as $hari) {
                    $keyDate = $prefixBulan . '-' . str_pad($hari, 2, '0', STR_PAD_LEFT);

                    // Filter: Hanya hitung jika hari kerja (Senin-Jumat) 
                    // dan bukan hari libur (kecuali libur Cuti Bersama)
                    $tglObj = \Carbon\Carbon::parse($keyDate);
                    $hData  = $hariLibur[$keyDate] ?? null;
                    $isH    = ($hData['holiday'] ?? false) === true
                        && !str_contains(implode(' ', (array)($hData['summary'] ?? [])), 'Cuti');

                    if (!$tglObj->isWeekend() && !$isH) {
                        if (isset($row[$keyDate]) && is_numeric($row[$keyDate])) {
                            $totalMinggu += (float) $row[$keyDate];
                        }
                    }
                }
                $row['week_' . ($idx + 1) . '_sum'] = $totalMinggu;
            }

            // ── Isi kolom summary ──
            $row['total_kehadiran'] = $totalKehadiran;
            $row['1']               = $jumlahHariLembur;
            $row['2']               = $jamEkstra;
            $row['total']           = $jumlahHariLembur + $jamEkstra;
            $row['lembur_khusus']   = $lemburKhusus;

            return $row;
        })->sortBy('NPK')->sortBy('BAGIAN')->values();

        // Kumpulkan info hari libur bulan ini (kecuali Cuti) untuk highlight di frontend
        $holidaysThisMonth = [];
        for ($d = 1; $d <= $jumlahHari; $d++) {
            $keyDate = $tanggalAwal->format('Y-m') . '-' . str_pad($d, 2, '0', STR_PAD_LEFT);
            if (isset($hariLibur[$keyDate]) && ($hariLibur[$keyDate]['holiday'] ?? false) === true) {
                $summary = $hariLibur[$keyDate]['summary'] ?? [];
                // Exclude hari libur yang mengandung kata "Cuti"
                if (!str_contains(implode(' ', (array) $summary), 'Cuti')) {
                    $holidaysThisMonth[$d] = $summary;
                }
            }
        }

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
        $month = \Carbon\Carbon::parse($date)->format('Y-m');

        $filename = 'overtime_calendar_' . $type . '_' . $month . '.xlsx';

        return Excel::download(
            new OvertimeCalendarExport($month, $type),
            $filename
        );
    }
}
