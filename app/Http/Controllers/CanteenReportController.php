<?php

namespace App\Http\Controllers;

use App\Models\CanteenReport;
use App\Models\CanteenTwoReport;
use App\Models\Outsource;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Illuminate\Support\Str;

class CanteenReportController extends Controller
{
    /** Jam mulai window istirahat utama. */
    protected string $mainBreakStart = '11:00:00';

    /** Jam selesai window istirahat utama. */
    protected string $mainBreakEnd = '13:30:00';

    /** Biaya per karyawan per makan (Rp). */
    protected int $costPerMeal = 7000;

    /** Label kantin untuk keperluan export PDF. */
    protected array $kantinLabels = [
        'Kantin 1' => 'KANTIN 1 (Diamond Chickres)',
        'Kantin 2' => 'KANTIN 2 (Pawon Ndoro Ayu)',
    ];

    public function index(): View
    {
        return view('canteen_report.index', [
            'departments'      => $this->getDepartments(),
            'defaultStartDate' => now()->format('Y-m-d'),
            'defaultEndDate'   => now()->format('Y-m-d'),
            'costPerMeal'      => $this->costPerMeal,
        ]);
    }

    /**
     * KPI + rekap total karyawan & biaya per kantin, per tanggal, per window.
     */
    public function summaryData(Request $request): JsonResponse
    {
        [$start, $end, $dept] = $this->parseFilters($request);

        $combined = $this->getCombinedScans($start, $end, $dept);

        $kantin1 = $combined->where('kantin', 'Kantin 1');
        $kantin2 = $combined->where('kantin', 'Kantin 2');

        $totalKantin1  = $kantin1->count();
        $totalKantin2  = $kantin2->count();
        $uniqueKantin1 = $kantin1->pluck('npk')->unique()->count();
        $uniqueKantin2 = $kantin2->pluck('npk')->unique()->count();

        $costKantin1     = $totalKantin1 * $this->costPerMeal;
        $costKantin2     = $totalKantin2 * $this->costPerMeal;
        $grandTotalCost  = $costKantin1 + $costKantin2;

        $anomalies = $this->detectAnomalies($combined);

        // Rekap per tanggal (untuk chart & tabel)
        $recapPerDate = $combined
            ->groupBy(fn($row) => $row['date'])
            ->map(function (Collection $rows, $date) {
                $k1     = $rows->where('kantin', 'Kantin 1')->count();
                $k2     = $rows->where('kantin', 'Kantin 2')->count();
                $utama  = $rows->where('window', 'Utama')->count();
                $lembur = $rows->where('window', 'Lembur')->count();

                return [
                    'date'     => $date,
                    'kantin_1' => $k1,
                    'kantin_2' => $k2,
                    'total'    => $k1 + $k2,
                    'utama'    => $utama,
                    'lembur'   => $lembur,
                    'cost'     => ($k1 + $k2) * $this->costPerMeal,
                ];
            })
            ->sortBy('date')
            ->values();

        $recapPerWindow = [
            'kantin_1_utama'  => $kantin1->where('window', 'Utama')->count(),
            'kantin_1_lembur' => $kantin1->where('window', 'Lembur')->count(),
            'kantin_2_utama'  => $kantin2->where('window', 'Utama')->count(),
            'kantin_2_lembur' => $kantin2->where('window', 'Lembur')->count(),
        ];

        return response()->json([
            'total_kantin_1'   => $totalKantin1,
            'total_kantin_2'   => $totalKantin2,
            'unique_kantin_1'  => $uniqueKantin1,
            'unique_kantin_2'  => $uniqueKantin2,
            'cost_kantin_1'    => $costKantin1,
            'cost_kantin_2'    => $costKantin2,
            'grand_total_cost' => $grandTotalCost,
            'total_anomali'    => $anomalies->count(),
            'recap_per_date'   => $recapPerDate,
            'recap_per_window' => $recapPerWindow,
        ]);
    }

    /**
     * Detail per scan, dipakai oleh DataTable tab "Detail Scan".
     */
    public function detailData(Request $request): JsonResponse
    {
        [$start, $end, $dept] = $this->parseFilters($request);
        $kantin = $this->parseKantinFilter($request);
        $window = $this->parseWindowFilter($request);

        $combined = $this->getCombinedScans($start, $end, $dept, $kantin);

        if ($window) {
            $combined = $combined->where('window', $window);
        }

        // Departemen di tab Detail Scan diambil live dari cii.DEPT, bukan dari
        // kolom dept yang tersimpan di baris canteen/canteen_twos:
        // NPK -> union(BIODATA, BIODATA_KELUAR) -> ID_DEPT -> join DEPT -> DEPARTEMENT.
        $deptMap = $this->getDeptMapForNpks($combined->pluck('npk'));

        $data = $combined
            ->sortBy([['date', 'asc'], ['created_at', 'asc']])
            ->values()
            ->map(fn($row) => [
                'id'         => $row['id'],
                'date'       => $row['date'],
                'jam'        => $row['created_at'] ? Carbon::parse($row['created_at'])->format('H:i:s') : '-',
                'npk'        => $row['npk'],
                'name'       => $row['name'],
                'dept'       => $deptMap[$row['npk']] ?? ($row['dept'] ?: '-'),
                'kantin'     => $row['kantin'],
                'canteen_no' => $row['canteen_no'],
                'window'     => $row['window'],
            ]);

        return response()->json(['data' => $data]);
    }

    /**
     * Cek duplikat / anomali scan, dipakai oleh DataTable tab "Cek Duplikat".
     */
    public function duplicateData(Request $request): JsonResponse
    {
        [$start, $end, $dept] = $this->parseFilters($request);

        $combined  = $this->getCombinedScans($start, $end, $dept);
        $anomalies = $this->detectAnomalies($combined);

        return response()->json(['data' => $anomalies->values()]);
    }

    /**
     * Pindahkan satu baris scan dari Kantin 1 <-> Kantin 2.
     * Dipakai untuk memperbaiki kasus salah kantin / duplikat antar kantin.
     */
    public function moveScan(Request $request): JsonResponse
    {
        $id   = $request->input('id');
        $from = $request->input('from');

        if (! $id || ! in_array($from, ['Kantin 1', 'Kantin 2'], true)) {
            return response()->json(['message' => 'Parameter tidak valid.'], 422);
        }

        $sourceModel = $from === 'Kantin 1' ? CanteenReport::class : CanteenTwoReport::class;
        $targetModel = $from === 'Kantin 1' ? CanteenTwoReport::class : CanteenReport::class;

        $row = $sourceModel::find($id);

        if (! $row) {
            return response()->json(['message' => 'Data scan tidak ditemukan.'], 404);
        }

        DB::transaction(function () use ($row, $targetModel) {
            // Nonaktifkan auto-timestamp Eloquent supaya created_at & updated_at
            // benar-benar memakai nilai asli dari baris sumber, bukan waktu saat ini.
            $newRow = new $targetModel();
            $newRow->timestamps = false;
            $newRow->canteen_no = $row->canteen_no;
            $newRow->npk        = $row->npk;
            $newRow->name       = $row->name;
            $newRow->dept       = $row->dept;
            $newRow->date       = $row->date;
            $newRow->created_at = $row->created_at;
            $newRow->updated_at = $row->updated_at;
            $newRow->save();

            $row->delete();
        });

        return response()->json(['message' => 'Data scan berhasil dipindahkan.']);
    }

    /**
     * Hapus satu baris scan (dipakai untuk membersihkan duplikat scan pada kantin & window yang sama).
     */
    public function deleteScan(Request $request): JsonResponse
    {
        $id     = $request->input('id');
        $kantin = $request->input('kantin');

        if (! $id || ! in_array($kantin, ['Kantin 1', 'Kantin 2'], true)) {
            return response()->json(['message' => 'Parameter tidak valid.'], 422);
        }

        $model = $kantin === 'Kantin 1' ? CanteenReport::class : CanteenTwoReport::class;
        $row   = $model::find($id);

        if (! $row) {
            return response()->json(['message' => 'Data scan tidak ditemukan.'], 404);
        }

        $row->delete();

        return response()->json(['message' => 'Data scan berhasil dihapus.']);
    }

    /**
     * Ambil & gabungkan data scan dari tabel canteen (Kantin 1) dan
     * canteen_twos (Kantin 2), lalu tandai window istirahat masing-masing baris.
     *
     * Filter departemen dilakukan berbasis NPK: nama departemen dicari ID_DEPT-nya
     * di cii.DEPT, lalu ID_DEPT tersebut dicari NPK-nya di union cii.BIODATA &
     * cii.BIODATA_KELUAR, dan kumpulan NPK itulah yang dipakai untuk memfilter
     * tabel canteen / canteen_twos (bukan kolom dept yang tersimpan di baris scan).
     *
     * $kantin: null (semua), 'Kantin 1', atau 'Kantin 2'.
     */
    protected function getCombinedScans(string $start, string $end, ?string $dept, ?string $kantin = null): Collection
    {
        $npksForDept = $dept ? $this->getNpksByDept($dept) : null;

        $rows1 = collect();
        $rows2 = collect();

        if ($kantin !== 'Kantin 2') {
            $q1 = CanteenReport::whereBetween('date', [$start, $end]);
            if ($npksForDept !== null && ! empty($npksForDept)) {
                $q1->whereRaw('npk IN (' . $this->quotedSqlList($npksForDept) . ')');
            }
            $rows1 = $q1->get()->map(fn($r) => $this->mapRow($r, 'Kantin 1'));
        }

        if ($kantin !== 'Kantin 1') {
            $q2 = CanteenTwoReport::whereBetween('date', [$start, $end]);
            if ($npksForDept !== null && ! empty($npksForDept)) {
                $q2->whereRaw('npk IN (' . $this->quotedSqlList($npksForDept) . ')');
            }
            $rows2 = $q2->get()->map(fn($r) => $this->mapRow($r, 'Kantin 2'));
        }

        return $rows1->concat($rows2);
    }

    protected function mapRow($row, string $kantinLabel): array
    {
        return [
            'id'          => $row->id,
            'canteen_no'  => $row->canteen_no,
            'npk'         => $row->npk,
            'name'        => $row->name,
            'dept'        => $row->dept,
            'date'        => $row->date instanceof Carbon ? $row->date->format('Y-m-d') : (string) $row->date,
            'created_at'  => $row->created_at,
            'kantin'      => $kantinLabel,
            'window'      => $this->resolveWindow($row->created_at),
        ];
    }

    /**
     * Tentukan window istirahat berdasarkan jam scan (created_at).
     * 11:00:00 - 13:30:00 => Utama, selain itu => Lembur.
     */
    protected function resolveWindow($createdAt): string
    {
        if (! $createdAt) {
            return 'Lembur';
        }

        $time = Carbon::parse($createdAt)->format('H:i:s');

        return ($time >= $this->mainBreakStart && $time <= $this->mainBreakEnd) ? 'Utama' : 'Lembur';
    }

    /**
     * Deteksi anomali scan:
     * 1. Duplikat Antar Kantin  - karyawan scan di Kantin 1 & Kantin 2 pada window yang sama (tidak boleh).
     * 2. Duplikat Scan di Kantin Sama - karyawan scan >1x di kantin yang sama pada window yang sama.
     * 3. Melebihi 2x Scan/Hari - total scan (gabungan 2 kantin, semua window) untuk 1 karyawan pada 1 tanggal > 2x.
     */
    protected function detectAnomalies(Collection $combined): Collection
    {
        $anomalies = collect();

        $byNpkDate = $combined->groupBy(fn($row) => $row['npk'] . '|' . $row['date']);

        foreach ($byNpkDate as $key => $rows) {
            [$npk, $date] = explode('|', $key);
            $name = $rows->first()['name'];
            $dept = $rows->first()['dept'];

            // 3. Total scan > 2 dalam sehari (seharusnya max 1x utama + 1x lembur)
            if ($rows->count() > 2) {
                $anomalies->push([
                    'npk'    => $npk,
                    'name'   => $name,
                    'dept'   => $dept,
                    'date'   => $date,
                    'window' => '-',
                    'type'   => 'Melebihi 2x Scan/Hari',
                    'detail' => 'Total scan: ' . $rows->count() . ' (' . $rows->pluck('kantin')->implode(', ') . ')',
                    'items'  => $this->mapAnomalyItems($rows),
                ]);
            }

            foreach ($rows->groupBy('window') as $window => $windowRows) {
                $kantinInWindow = $windowRows->pluck('kantin')->unique();

                // 1. Scan di 2 kantin berbeda pada window yang sama
                if ($kantinInWindow->count() > 1) {
                    $anomalies->push([
                        'npk'    => $npk,
                        'name'   => $name,
                        'dept'   => $dept,
                        'date'   => $date,
                        'window' => $window,
                        'type'   => 'Duplikat Antar Kantin',
                        'detail' => 'Scan di ' . $kantinInWindow->implode(' & ') . ' pada window ' . $window,
                        'items'  => $this->mapAnomalyItems($windowRows),
                    ]);
                }

                // 2. Scan berulang di kantin yang sama pada window yang sama
                foreach ($windowRows->groupBy('kantin') as $kantinLabel => $kantinRows) {
                    if ($kantinRows->count() > 1) {
                        $anomalies->push([
                            'npk'    => $npk,
                            'name'   => $name,
                            'dept'   => $dept,
                            'date'   => $date,
                            'window' => $window,
                            'type'   => 'Duplikat Scan di Kantin Sama',
                            'detail' => $kantinLabel . ' discan ' . $kantinRows->count() . 'x pada window ' . $window,
                            'items'  => $this->mapAnomalyItems($kantinRows),
                        ]);
                    }
                }
            }
        }

        return $anomalies;
    }

    /**
     * Bentuk daftar item (id, kantin, jam) dari sekumpulan baris scan, dipakai
     * di tab "Cek Duplikat / Anomali" untuk tombol Pindah Kantin & Hapus per baris.
     */
    protected function mapAnomalyItems(Collection $rows): array
    {
        return $rows->map(fn($r) => [
            'id'         => $r['id'],
            'kantin'     => $r['kantin'],
            'canteen_no' => $r['canteen_no'],
            'jam'        => $r['created_at'] ? Carbon::parse($r['created_at'])->format('H:i:s') : '-',
        ])->values()->all();
    }

    protected function parseFilters(Request $request): array
    {
        $start = $request->input('start_date', now()->format('Y-m-d'));
        $end   = $request->input('end_date', now()->format('Y-m-d'));
        $dept  = $request->input('dept') ?: null;

        return [$start, $end, $dept];
    }

    /**
     * Filter kantin untuk tab Detail Scan: null (semua), 'Kantin 1', atau 'Kantin 2'.
     */
    protected function parseKantinFilter(Request $request): ?string
    {
        $kantin = $request->input('kantin');

        return in_array($kantin, ['Kantin 1', 'Kantin 2'], true) ? $kantin : null;
    }

    /**
     * Filter window istirahat untuk tab Detail Scan: null (semua), 'Utama', atau 'Lembur'.
     */
    protected function parseWindowFilter(Request $request): ?string
    {
        $window = $request->input('window');

        return in_array($window, ['Utama', 'Lembur'], true) ? $window : null;
    }

    /**
     * Ambil daftar nama departemen (untuk dropdown filter) langsung dari cii.DEPT.
     */
    protected function getDepartments(): array
    {
        return DB::connection('cii')
            ->table('DEPT')
            ->whereNotNull('DEPARTEMENT')
            ->where('DEPARTEMENT', '!=', '')
            ->distinct()
            ->orderBy('DEPARTEMENT')
            ->pluck('DEPARTEMENT')
            ->values()
            ->all();
    }

    /**
     * Ambil daftar NPK milik satu departemen, dengan alur:
     * 1. Ambil ID_DEPT dari cii.DEPT berdasarkan nama departemen.
     * 2. Cari NPK-NPK yang memiliki ID_DEPT tersebut dari cii.BIODATA UNION cii.BIODATA_KELUAR.
     * Hasilnya dipakai untuk memfilter tabel canteen / canteen_twos.
     */
    protected function getNpksByDept(string $deptName): array
    {
        $deptIds = DB::connection('cii')
            ->table('DEPT')
            ->where('DEPARTEMENT', $deptName)
            ->pluck('ID_DEPT');

        if ($deptIds->isEmpty()) {
            return [];
        }

        // Gunakan pendekatan dengan temporary table atau subquery untuk menghindari
        // parameter binding yang berlebihan pada SQL Server.
        $deptIdList = $deptIds->implode(',');

        $biodataAll = DB::connection('cii')
            ->table('BIODATA')
            ->select('NPK', 'ID_DEPT')
            ->union(
                DB::connection('cii')->table('BIODATA_KELUAR')->select('NPK', 'ID_DEPT')
            );

        return DB::connection('cii')
            ->table(DB::raw('(' . $biodataAll->toSql() . ') AS biodata_all'))
            ->mergeBindings($biodataAll)
            ->whereRaw("biodata_all.ID_DEPT IN ({$deptIdList})")
            ->select('biodata_all.NPK')
            ->distinct()
            ->pluck('NPK')
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    /**
     * Bentuk map NPK => nama departemen (untuk tab Detail Scan), dengan alur:
     * NPK -> union(cii.BIODATA, cii.BIODATA_KELUAR) -> ID_DEPT -> join cii.DEPT -> DEPARTEMENT.
     */
    protected function getDeptMapForNpks(Collection $npks): array
    {
        $npks = $npks->filter()->unique()->values();

        if ($npks->isEmpty()) {
            return [];
        }

        $map = [];

        // Gunakan pendekatan dengan temporary table atau subquery untuk menghindari
        // parameter binding yang berlebihan pada SQL Server.
        $npkList = $npks->implode("','");
        $npkList = "'" . $npkList . "'";

        $biodataAll = DB::connection('cii')
            ->table('BIODATA')
            ->select('NPK', 'ID_DEPT')
            ->union(
                DB::connection('cii')->table('BIODATA_KELUAR')->select('NPK', 'ID_DEPT')
            );

        $rows = DB::connection('cii')
            ->table(DB::raw('(' . $biodataAll->toSql() . ') AS biodata_all'))
            ->mergeBindings($biodataAll)
            ->join('DEPT', 'DEPT.ID_DEPT', '=', 'biodata_all.ID_DEPT')
            ->whereRaw("biodata_all.NPK IN ({$npkList})")
            ->select('biodata_all.NPK', 'DEPT.DEPARTEMENT')
            ->get();

        foreach ($rows as $row) {
            $map[$row->NPK] = $row->DEPARTEMENT;
        }

        return $map;
    }

    protected function kantinModel(string $kantin): string
    {
        return $kantin === 'Kantin 1' ? CanteenReport::class : CanteenTwoReport::class;
    }

    /**
     * Bentuk daftar literal SQL "'a','b','c'" dari array nilai, untuk dipakai
     * pada whereRaw(... IN (...)) / NOT IN (...) supaya tidak membuat bound
     * parameter per elemen (SQL Server hanya mendukung maksimal 2100
     * parameter per query). Setiap nilai di-escape (single quote digandakan)
     * supaya tetap aman dari SQL injection.
     */
    protected function quotedSqlList(array $values): string
    {
        $escaped = array_map(
            fn($v) => "'" . str_replace("'", "''", (string) $v) . "'",
            $values
        );

        return implode(',', $escaped);
    }
 
/* =========================================================================
 * 1) TAMBAH DATA MANUAL (Employee / Outsource yang belum tercatat di canteen)
 * ========================================================================= */

    /**
     * Select2 AJAX: daftar NPK employee (tabel BIODATA), difilter oleh kata
     * kunci pencarian (NPK / NAMA_KARYAWAN).
     */
    public function employeeOptions(Request $request): JsonResponse
    {
        $q = trim((string) $request->input('q', ''));

        $query = DB::connection('cii')
            ->table('BIODATA')
            ->when($q !== '', function ($qq) use ($q) {
                $qq->where(function ($w) use ($q) {
                    $w->where('NPK', 'like', "%{$q}%")
                        ->orWhere('NAMA_KARYAWAN', 'like', "%{$q}%");
                });
            })
            ->select('NPK', 'NAMA_KARYAWAN')
            ->distinct()
            ->orderBy('NPK')
            ->get();

        return response()->json([
            'results' => $query->map(fn($r) => [
                'id'   => $r->NPK,
                'text' => "{$r->NPK} - {$r->NAMA_KARYAWAN}",
            ]),
        ]);
    }

    /**
     * Select2 AJAX: daftar NPK outsource (tabel outsources, void kosong/aktif),
     * difilter oleh kata kunci pencarian (NPK / NAMA).
     */
    public function outsourceOptions(Request $request): JsonResponse
    {
        $q = trim((string) $request->input('q', ''));

        $query = Outsource::query()
            ->where(function ($w) {
                $w->whereNull('void')->orWhere('void', 'false');
            })
            ->when($q !== '', function ($qq) use ($q) {
                $qq->where(function ($w) use ($q) {
                    $w->where('NPK', 'like', "%{$q}%")
                        ->orWhere('NAMA', 'like', "%{$q}%");
                });
            })
            ->orderBy('NPK')
            ->get();

        return response()->json([
            'results' => $query->map(fn($r) => [
                'id'   => $r->NPK,
                'text' => "{$r->NPK} - {$r->NAMA}" . ($r->VENDOR ? " ({$r->VENDOR})" : ''),
            ]),
        ]);
    }

    /**
     * Simpan satu baris data manual (Employee / Outsource) ke canteen / canteen_twos.
     * Dipakai oleh modal "Tambah Data Manual" di index_canteen_report.
     */
    public function manualStore(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'kantin'   => 'required|in:Kantin 1,Kantin 2',
            'category' => 'required|in:employee,outsource',
            'npk'      => 'required|string',
            'date'     => 'required|date',
            'time'     => 'nullable|date_format:H:i',
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => $validator->errors()->first()], 422);
        }

        $kantin   = $request->input('kantin');
        $category = $request->input('category');
        $npk      = trim($request->input('npk'));
        $date     = $request->input('date');
        $time     = $request->input('time') ?: now()->format('H:i');

        $canteenNo  = $kantin === 'Kantin 1' ? '1' : '2';

        // Resolve nama & dept/vendor sesuai kategori.
        if ($category === 'employee') {
            $person = DB::connection('cii')
                ->table('BIODATA')
                ->where('NPK', $npk)
                ->select('NAMA_KARYAWAN', 'BAG')
                ->first();

            if (! $person) {
                return response()->json(['message' => 'NPK employee tidak ditemukan.'], 404);
            }

            $name = $person->NAMA_KARYAWAN;
            $dept = $person->BAG;
        } else {
            $person = Outsource::where('NPK', $npk)->first();

            if (! $person) {
                return response()->json(['message' => 'NPK outsource tidak ditemukan.'], 404);
            }

            $name = $person->NAMA;
            $dept = $person->VENDOR;
        }

        $modelClass = $this->kantinModel($kantin);
        $dateTime   = Carbon::parse("{$date} {$time}:00");

        $row = new $modelClass();
        $row->timestamps  = false; // supaya created_at/updated_at pakai nilai yang kita tentukan
        $row->canteen_no  = $canteenNo;
        $row->npk         = $npk;
        $row->name        = $name;
        $row->dept        = $dept;
        $row->date        = $date;
        $row->created_at  = $dateTime;
        $row->updated_at  = $dateTime;
        $row->save();

        return response()->json(['message' => "Data {$name} berhasil ditambahkan ke {$kantin}."]);
    }
 
/* =========================================================================
 * 2) TEMPLATE EXCEL & IMPORT SHIFT SIANG/MALAM
 * ========================================================================= */

    /**
     * Download template excel untuk import data shift (npk, nama_karyawan, bagian,
     * jam_masuk, jam_pulang).
     */
    public function downloadTemplate()
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Template Canteen');

        $headers = ['npk', 'nama_karyawan', 'bagian', 'jam_masuk', 'jam_pulang'];
        $sheet->fromArray($headers, null, 'A1');
        $sheet->getStyle('A1:E1')->getFont()->setBold(true);
        foreach (['A', 'B', 'C', 'D', 'E'] as $col) {
            $sheet->getColumnDimension($col)->setWidth(20);
        }

        $writer = new Xlsx($spreadsheet);

        $fileName = 'template_canteen_import.xlsx';
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment;filename="' . $fileName . '"');
        header('Cache-Control: max-age=0');

        $writer->save('php://output');
        exit;
    }

    /**
     * Import file excel (npk, nama_karyawan, bagian, jam_masuk, jam_pulang) ke
     * canteen / canteen_twos sesuai kantin & shift yang dipilih pada modal import.
     * - Shift Siang -> created_at/updated_at = tanggal terpilih 18:00:00
     * - Shift Malam -> created_at/updated_at = tanggal terpilih 22:30:00
     * Kolom jam_masuk & jam_pulang pada file hanya untuk referensi/pengecekan,
     * tidak disimpan karena tabel canteen/canteen_twos tidak punya kolom tsb.
     */
    public function importShift(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'file'   => 'required|mimes:xlsx,xls,csv',
            'shift'  => 'required|in:siang,malam',
            'kantin' => 'required|in:Kantin 1,Kantin 2',
            'date'   => 'required|date',
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => $validator->errors()->first()], 422);
        }

        $shift  = $request->input('shift');
        $kantin = $request->input('kantin');
        $date   = $request->input('date');
        $time   = $shift === 'siang' ? '18:00:00' : '22:30:00';
        $dateTime = Carbon::parse("{$date} {$time}");

        // Kantin 1 -> canteen (CanteenReport), Kantin 2 -> canteen_twos (CanteenTwoReport)
        $modelClass = $this->kantinModel($kantin);
        $canteenNo  = $kantin === 'Kantin 1' ? '1' : '2';

        try {
            $spreadsheet = IOFactory::load($request->file('file')->getRealPath());
        } catch (\Throwable $e) {
            return response()->json(['message' => 'File tidak dapat dibaca: ' . $e->getMessage()], 422);
        }

        $rows = $spreadsheet->getActiveSheet()->toArray(null, true, true, true);

        $errors   = [];
        $created  = 0;
        $updated  = 0;

        DB::beginTransaction();
        try {
            foreach ($rows as $i => $r) {
                if ($i == 1) {
                    continue; // skip header
                }

                $npk    = trim((string) ($r['A'] ?? ''));
                $nama   = trim((string) ($r['B'] ?? ''));
                $bagian = trim((string) ($r['C'] ?? ''));

                if ($npk === '' && $nama === '') {
                    continue; // baris kosong
                }

                if ($npk === '' || $nama === '') {
                    $errors[] = "Baris $i: npk/nama_karyawan wajib diisi, dilewati.";
                    continue;
                }

                // Cari baris yang sudah ada untuk NPK + tanggal yang sama.
                $row = $modelClass::where('npk', $npk)
                    ->where('date', $date)
                    ->first();

                $isNew = false;
                if (! $row) {
                    $row   = new $modelClass();
                    $isNew = true;
                }

                $row->timestamps = false; // supaya created_at/updated_at pakai nilai yang kita tentukan
                $row->canteen_no = $canteenNo;
                $row->npk        = $npk;
                $row->name       = $nama;
                $row->dept       = $bagian ?: null;
                $row->date       = $date;
                $row->created_at = $dateTime;
                $row->updated_at = $dateTime;
                $row->save();

                $isNew ? $created++ : $updated++;
            }

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json(['message' => 'Gagal import: ' . $e->getMessage()], 500);
        }

        if (! empty($errors)) {
            session()->flash('import_errors', $errors);
        }

        return response()->json([
            'message' => "Import selesai. $created baris baru ditambahkan, $updated baris diperbarui, ke $kantin (shift " . ucfirst($shift) . ").",
        ]);
    }
 
 
/* =========================================================================
 * 3) EXPORT REKAP PDF ("Realisasi Kantin ...")
 * ========================================================================= */

    /**
     * Export rekap harian per kantin dalam format PDF seperti contoh
     * "REALISASI KANTIN ...". Difilter berdasarkan range tanggal & kantin.
     *
     * Formula per baris (tervalidasi dari contoh):
     *   TOTAL = JUMLAH SCAN + TIDAK SCAN (security OS) + SIFT MALAM + SIFT SIANG
     *   TOTAL (Rp) = TOTAL x HARGA NASI
     *
     * - JUMLAH SCAN        = baris dengan canteen_no asli dari mesin scan
     *                         (bukan 'MANUAL' & bukan 'IMPORT')
     * - TIDAK SCAN         = baris dari tombol "Tambah Data Manual" (canteen_no = 'MANUAL')
     * - SIFT MALAM / SIANG = baris dari import excel (canteen_no = 'IMPORT'),
     *                         dibedakan dari jam di created_at (18:00 siang / 22:30 malam)
     */
    /**
     * Export rekap harian per kantin dalam format PDF seperti contoh
     * "REALISASI KANTIN ...". Difilter berdasarkan range tanggal & kantin.
     *
     * Formula per baris (tervalidasi dari contoh):
     *   TOTAL = JUMLAH SCAN + TIDAK SCAN (security OS) + SIFT MALAM + SIFT SIANG
     *   TOTAL (Rp) = TOTAL x HARGA NASI
     *
     * Catatan: sejak importShift() menyimpan canteen_no hanya sebagai angka
     * kantin ('1' / '2'), baris hasil import TIDAK BISA lagi dibedakan dari
     * canteen_no. Jadi baris shift (Sift Siang/Malam) sekarang dideteksi dari
     * JAM di created_at (18:00:00 = siang, 22:30:00 = malam) — jam ini hanya
     * dihasilkan oleh proses import, sehingga tetap aman dipakai sebagai penanda.
     *
     * - TIDAK SCAN         = baris dari tombol "Tambah Data Manual" (canteen_no = 'MANUAL')
     * - SIFT SIANG / MALAM = baris dengan created_at jam persis 18:00:00 / 22:30:00
     *                        (hasil import), dan canteen_no BUKAN 'MANUAL'
     * - JUMLAH SCAN        = sisanya (bukan manual & bukan import)
     */
    public function exportRekapPdf(Request $request)
    {

        $userName = Auth::user()->name ?? 'Admin';
        $request->validate([
            'start_date' => 'required|date',
            'end_date'   => 'required|date',
            'kantin'     => 'required|in:Kantin 1,Kantin 2',
        ]);

        $start  = $request->input('start_date');
        $end    = $request->input('end_date');
        $kantin = $request->input('kantin');
        $modelClass = $this->kantinModel($kantin);

        $rows = $modelClass::whereBetween('date', [$start, $end])->get();

        $period = collect();
        for ($d = Carbon::parse($start); $d->lte(Carbon::parse($end)); $d->addDay()) {
            $period->push($d->format('Y-m-d'));
        }

        $grandTotal = 0;

        $report = $period->map(function ($date) use ($rows, &$grandTotal) {
            $dayRows = $rows->filter(fn($r) => Carbon::parse($r->date)->format('Y-m-d') === $date);

            $isManual = fn($r) => substr($r->npk, 0, 2) === 'O-';
            $shiftTime = fn($r) => Carbon::parse($r->created_at)->format('H:i:s');

            $tidakScan = $dayRows->filter($isManual)->count();
            $siftSiang = $dayRows->filter(fn($r) => ! $isManual($r) && $shiftTime($r) === '18:00:00')->count();
            $siftMalam = $dayRows->filter(fn($r) => ! $isManual($r) && $shiftTime($r) === '22:30:00')->count();

            $jumlahScan = $dayRows->filter(function ($r) use ($isManual, $shiftTime) {
                return ! $isManual($r) && ! in_array($shiftTime($r), ['18:00:00', '22:30:00'], true);
            })->count();

            $total     = $jumlahScan + $tidakScan + $siftMalam + $siftSiang;
            $totalCost = $total * $this->costPerMeal;
            $grandTotal += $totalCost;

            $carbonDate = Carbon::parse($date);

            return [
                'date_label'  => $carbonDate->locale('id')->translatedFormat('l, d F Y'),
                'is_weekend'  => $carbonDate->isWeekend(),
                'jumlah_scan' => $jumlahScan,
                'tidak_scan'  => $tidakScan,
                'sift_malam'  => $siftMalam,
                'sift_siang'  => $siftSiang,
                'total'       => $total,
                'harga_nasi'  => $this->costPerMeal,
                'total_cost'  => $totalCost,
            ];
        });

        $periode = Carbon::parse($start)->locale('id')->translatedFormat('d F Y')
            . ' s.d. '
            . Carbon::parse($end)->locale('id')->translatedFormat('d F Y');

        $pdf = Pdf::loadView('canteen_report.rekap_pdf', [
            'kantinLabel' => $this->kantinLabels[$kantin] ?? $kantin,
            'periode'     => $periode,
            'report'      => $report,
            'grandTotal'  => $grandTotal,
            'userName'    => Str::upper($userName),
        ])->setPaper('a4', 'portrait');

        $fileName = 'Realisasi_Kantin_' . str_replace(' ', '_', $kantin) . '_' . $start . '_sd_' . $end . '.pdf';

        return $pdf->download($fileName);
    }
}
