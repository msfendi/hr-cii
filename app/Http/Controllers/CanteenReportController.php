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
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
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

    /**
     * Bentuk map NPK => nama karyawan (untuk export Excel rekap kantin), dengan alur
     * yang sama seperti getDeptMapForNpks(): karena tabel canteen/canteen_twos ada di
     * connection 'canteen' sedangkan BIODATA/BIODATA_KELUAR ada di connection 'cii',
     * keduanya tidak bisa di-JOIN langsung lewat SQL (beda connection/server).
     *
     * Jadi "join"-nya dilakukan di level aplikasi:
     * 1. NPK-NPK dari baris canteen/canteen_twos dikumpulkan dulu (unique).
     * 2. NPK tsb dipakai untuk query connection 'cii' -> union(BIODATA, BIODATA_KELUAR)
     *    -> ambil NPK & NAMA_KARYAWAN.
     * 3. Hasilnya dibentuk jadi array asosiatif [NPK => NAMA_KARYAWAN] sebagai lookup map.
     * 4. Baris canteen/canteen_twos di-mapping ke nama lewat lookup map ini di PHP,
     *    bukan lewat JOIN SQL.
     */
    protected function getNameMapForNpks(Collection $npks): array
    {
        $npks = $npks->filter()->unique()->values();

        if ($npks->isEmpty()) {
            return [];
        }

        $npkList = $this->quotedSqlList($npks->all());

        $biodataAll = DB::connection('cii')
            ->table('BIODATA')
            ->select('NPK', 'NAMA_KARYAWAN')
            ->union(
                DB::connection('cii')->table('BIODATA_KELUAR')->select('NPK', 'NAMA_KARYAWAN')
            );

        $rows = DB::connection('cii')
            ->table(DB::raw('(' . $biodataAll->toSql() . ') AS biodata_all'))
            ->mergeBindings($biodataAll)
            ->whereRaw("biodata_all.NPK IN ({$npkList})")
            ->select('biodata_all.NPK', 'biodata_all.NAMA_KARYAWAN')
            ->get();

        $map = [];
        foreach ($rows as $row) {
            $map[$row->NPK] = $row->NAMA_KARYAWAN;
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
     * - Shift Siang -> created_at/updated_at & date = tanggal terpilih, jam 18:00:00
     * - Shift Malam -> jam scan tercatat 00:30:00, yang artinya baris tersebut
     *   secara kalender adalah keesokan hari dari tanggal yang dipilih di form
     *   (mis. shift malam untuk tanggal 5 Agustus dicatat sebagai tanggal 6
     *   Agustus jam 00:30:00). Jadi date & created_at/updated_at = tanggal
     *   terpilih + 1 hari, jam 00:30:00.
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

        $shift     = $request->input('shift');
        $kantin    = $request->input('kantin');
        $inputDate = $request->input('date');

        // Shift Malam: jam 00:30 berarti baris tersebut jatuh di tanggal
        // keesokan harinya dari tanggal yang dipilih pada form import.
        if ($shift === 'malam') {
            $date = Carbon::parse($inputDate)->addDay()->format('Y-m-d');
            $time = '00:30:00';
        } else {
            $date = $inputDate;
            $time = '18:00:00';
        }

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

        $shiftInfo = $shift === 'malam'
            ? 'shift Malam, tercatat tanggal ' . Carbon::parse($date)->locale('id')->translatedFormat('d F Y') . ' jam 00:30'
            : 'shift Siang, tanggal ' . Carbon::parse($date)->locale('id')->translatedFormat('d F Y');

        return response()->json([
            'message' => "Import selesai. $created baris baru ditambahkan, $updated baris diperbarui, ke $kantin ($shiftInfo).",
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
     * Catatan: sejak importShift() menyimpan canteen_no hanya sebagai angka
     * kantin ('1' / '2'), baris hasil import TIDAK BISA lagi dibedakan dari
     * canteen_no. Jadi baris shift (Sift Siang/Malam) sekarang dideteksi dari
     * JAM di created_at (18:00:00 = siang, 00:30:00 = malam) — jam ini hanya
     * dihasilkan oleh proses import, sehingga tetap aman dipakai sebagai penanda.
     *
     * - TIDAK SCAN         = baris dari tombol "Tambah Data Manual" (canteen_no = 'MANUAL')
     * - SIFT SIANG         = baris dengan created_at jam persis 18:00:00 (hasil import),
     *                        pada tanggal yang sama dengan baris rekap.
     * - SIFT MALAM         = baris dengan created_at jam persis 00:30:00 (hasil import)
     *                        yang tersimpan pada tanggal KEESOKAN HARI dari baris
     *                        rekap (shift malam dimulai malam ini, makan tercatat
     *                        lewat tengah malam), sehingga diambil dari data H+1.
     * - JUMLAH SCAN        = sisanya (bukan manual, bukan shift siang, bukan shift malam)
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

        [$report, $grandTotal, $periode] = $this->buildRekapReport($start, $end, $kantin);

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

    /**
     * Bentuk data rekap harian (sheet Summary / rekap_pdf) untuk satu kantin & range
     * tanggal tertentu. Dipisah dari exportRekapPdf() supaya bisa dipakai bareng oleh
     * exportRekapExcel() tanpa duplikasi formula.
     *
     * Formula per baris (tervalidasi dari contoh):
     *   TOTAL = JUMLAH SCAN + TIDAK SCAN (security OS) + SIFT MALAM + SIFT SIANG
     *   TOTAL (Rp) = TOTAL x HARGA NASI
     *
     * @return array{0: Collection, 1: int, 2: string} [$report, $grandTotal, $periode]
     */
    protected function buildRekapReport(string $start, string $end, string $kantin): array
    {
        $modelClass = $this->kantinModel($kantin);

        // Ambil sampai H+1 dari $end supaya baris Shift Malam (00:30) milik
        // tanggal terakhir periode, yang tersimpan di tanggal keesokan harinya,
        // ikut terbaca.
        $queryEnd = Carbon::parse($end)->addDay()->format('Y-m-d');
        $rows = $modelClass::whereBetween('date', [$start, $queryEnd])->get();

        $period = collect();
        for ($d = Carbon::parse($start); $d->lte(Carbon::parse($end)); $d->addDay()) {
            $period->push($d->format('Y-m-d'));
        }

        $grandTotal = 0;

        $report = $period->map(function ($date) use ($rows, &$grandTotal) {
            $dayRows = $rows->filter(fn($r) => Carbon::parse($r->date)->format('Y-m-d') === $date);

            // Shift Malam: jam scan 00:30 tercatat di tanggal keesokan harinya,
            // tapi secara operasional adalah bagian dari shift malam tanggal ini
            // (mulai malam $date, makan tercatat lewat tengah malam).
            $nextDate    = Carbon::parse($date)->addDay()->format('Y-m-d');
            $nextDayRows = $rows->filter(fn($r) => Carbon::parse($r->date)->format('Y-m-d') === $nextDate);

            $isManual = fn($r) => substr($r->npk, 0, 2) === 'O-';
            $shiftTime = fn($r) => Carbon::parse($r->created_at)->format('H:i:s');

            $tidakScan = $dayRows->filter($isManual)->count();
            $siftSiang = $dayRows->filter(fn($r) => ! $isManual($r) && $shiftTime($r) === '18:00:00')->count();
            $siftMalam = $nextDayRows->filter(fn($r) => ! $isManual($r) && $shiftTime($r) === '00:30:00')->count();

            // Baris jam 00:30 yang jatuh pada $dayRows milik shift malam tanggal
            // SEBELUMNYA (sudah dihitung sebagai sift_malam di baris tanggal itu),
            // jadi harus dikecualikan di sini supaya tidak dobel hitung sebagai
            // jumlah_scan pada tanggal ini.
            $jumlahScan = $dayRows->filter(function ($r) use ($isManual, $shiftTime) {
                return ! $isManual($r) && ! in_array($shiftTime($r), ['18:00:00', '00:30:00'], true);
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

        return [$report, $grandTotal, $periode];
    }

/* =========================================================================
 * 4) EXPORT REKAP EXCEL (Summary + Normal Break + OS + Shift Siang + Shift Malam)
 * ========================================================================= */

    /**
     * Ambil baris canteen/canteen_twos untuk satu kantin & range tanggal, lalu
     * kelompokkan ke 4 kategori (Normal Break, OS, Shift Siang, Shift Malam) untuk
     * dipakai sebagai sheet detail pada export Excel.
     *
     * Kategorisasi per baris:
     * - OS           : NPK diawali 'O-'.
     * - Shift Siang  : bukan OS, jam created_at persis 18:00:00 (hasil importShift siang).
     * - Shift Malam  : bukan OS, jam created_at persis 00:30:00 (hasil importShift malam,
     *                  tersimpan di tanggal keesokan hari dari tanggal shift malam dimulai).
     * - Normal Break : bukan OS & bukan shift siang/malam, jam created_at ada di antara
     *                  mainBreakStart (11:00:00) s.d. mainBreakEnd (13:30:00).
     * Baris di luar 4 kategori di atas (mis. scan lembur di luar jam istirahat utama)
     * tidak masuk ke sheet manapun, karena tidak diminta.
     *
     * Nama karyawan diambil dari union cii.BIODATA & cii.BIODATA_KELUAR (via
     * getNameMapForNpks()), bukan dari kolom `name` yang tersimpan di baris scan, supaya
     * konsisten dengan sumber data HR. Jika NPK tidak ditemukan di HR (mis. NPK outsource
     * 'O-...'), fallback ke kolom `name` yang tersimpan.
     *
     * @return array{normal_break: Collection, os: Collection, shift_siang: Collection, shift_malam: Collection}
     */
    protected function getDetailRowsCategorized(string $start, string $end, string $kantin): array
    {
        $modelClass = $this->kantinModel($kantin);

        $rows = $modelClass::whereBetween('date', [$start, $end])
            ->orderBy('date')
            ->orderBy('created_at')
            ->get();

        $nameMap = $this->getNameMapForNpks($rows->pluck('npk')->filter()->unique()->values());

        $categorized = [
            'normal_break' => collect(),
            'os'           => collect(),
            'shift_siang'  => collect(),
            'shift_malam'  => collect(),
        ];

        foreach ($rows as $r) {
            $isOs = strtoupper(substr((string) $r->npk, 0, 2)) === 'O-';
            $time = $r->created_at ? Carbon::parse($r->created_at)->format('H:i:s') : null;

            $item = [
                'npk'    => $r->npk,
                'name'   => $nameMap[$r->npk] ?? ($r->name ?: '-'),
                'kantin' => $kantin,
                'date'   => $r->date instanceof Carbon ? $r->date->format('Y-m-d') : (string) $r->date,
                'time'   => $time,
                'cost'   => $this->costPerMeal,
            ];

            if ($isOs) {
                $categorized['os']->push($item);
                continue;
            }

            if ($time === '18:00:00') {
                $categorized['shift_siang']->push($item);
                continue;
            }

            if ($time === '00:30:00') {
                $categorized['shift_malam']->push($item);
                continue;
            }

            if ($time !== null && $time >= $this->mainBreakStart && $time <= $this->mainBreakEnd) {
                $categorized['normal_break']->push($item);
            }
        }

        return $categorized;
    }

    /**
     * Export rekap kantin dalam format Excel (5 sheet):
     * 1. Summary       - layout sama persis dengan rekap_pdf.blade.php.
     * 2. Normal Break  - detail scan pada jam istirahat utama.
     * 3. OS            - detail scan NPK outsource/security (diawali 'O-').
     * 4. Shift Siang   - detail hasil import shift siang (18:00:00).
     * 5. Shift Malam   - detail hasil import shift malam (00:30:00, tanggal H+1).
     *
     * Kolom sheet detail: No, NPK, Nama, Kantin, Tanggal, Waktu Scanning, Harga per Porsi,
     * dengan baris TOTAL PORSI di baris paling bawah masing-masing sheet.
     */
    public function exportRekapExcel(Request $request)
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

        [$report, $grandTotal, $periode] = $this->buildRekapReport($start, $end, $kantin);
        $categorized = $this->getDetailRowsCategorized($start, $end, $kantin);

        $spreadsheet = new Spreadsheet();

        $this->writeSummarySheet(
            $spreadsheet,
            $this->kantinLabels[$kantin] ?? $kantin,
            $periode,
            $report,
            $grandTotal,
            Str::upper($userName)
        );

        $sheetDefs = [
            'Normal Break' => $categorized['normal_break'],
            'OS'           => $categorized['os'],
            'Shift Siang'  => $categorized['shift_siang'],
            'Shift Malam'  => $categorized['shift_malam'],
        ];

        foreach ($sheetDefs as $title => $rows) {
            $this->writeDetailSheet($spreadsheet, $title, $rows);
        }

        $spreadsheet->setActiveSheetIndex(0);

        $writer = new Xlsx($spreadsheet);

        $fileName = 'Realisasi_Kantin_' . str_replace(' ', '_', $kantin) . '_' . $start . '_sd_' . $end . '.xlsx';

        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment;filename="' . $fileName . '"');
        header('Cache-Control: max-age=0');

        $writer->save('php://output');
        exit;
    }

    /**
     * Tulis sheet "Summary", mereplikasi layout rekap_pdf.blade.php: judul, info
     * kantin/periode, tabel rekap harian (baris weekend disorot merah muda), baris
     * grand total (disorot kuning), lalu blok tanda tangan di bagian bawah.
     */
    protected function writeSummarySheet(
        Spreadsheet $spreadsheet,
        string $kantinLabel,
        string $periode,
        Collection $report,
        int $grandTotal,
        string $userName
    ): void {
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Summary');

        $sheet->mergeCells('A1:I1');
        $sheet->setCellValue('A1', 'REALISASI KANTIN ' . Str::upper($kantinLabel));
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(13);
        $sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        $sheet->setCellValue('A3', 'KANTIN');
        $sheet->mergeCells('B3:I3');
        $sheet->setCellValue('B3', ': ' . $kantinLabel);

        $sheet->setCellValue('A4', 'PERIODE');
        $sheet->mergeCells('B4:I4');
        $sheet->setCellValue('B4', ': ' . $periode);

        $sheet->getStyle('A3:A4')->getFont()->setBold(true);

        $headerRow = 6;
        $headers = ['NO.', 'HARI, TANGGAL', 'JUMLAH SCAN', "TIDAK SCAN\n(security OS)", 'Sift Malam', 'Sift Siang', 'TOTAL', 'HARGA NASI', 'TOTAL'];
        $sheet->fromArray($headers, null, "A{$headerRow}");
        $sheet->getStyle("A{$headerRow}:I{$headerRow}")->getFont()->setBold(true);
        $sheet->getStyle("A{$headerRow}:I{$headerRow}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('F0F0F0');
        $sheet->getStyle("A{$headerRow}:I{$headerRow}")->getAlignment()
            ->setHorizontal(Alignment::HORIZONTAL_CENTER)
            ->setVertical(Alignment::VERTICAL_CENTER)
            ->setWrapText(true);

        $row = $headerRow + 1;
        foreach ($report->values() as $i => $r) {
            $sheet->setCellValue("A{$row}", $i + 1);
            $sheet->setCellValue("B{$row}", $r['date_label']);
            $sheet->setCellValue("C{$row}", $r['jumlah_scan'] ?: null);
            $sheet->setCellValue("D{$row}", $r['tidak_scan'] ?: null);
            $sheet->setCellValue("E{$row}", $r['sift_malam'] ?: null);
            $sheet->setCellValue("F{$row}", $r['sift_siang'] ?: null);
            $sheet->setCellValue("G{$row}", $r['total']);
            $sheet->setCellValue("H{$row}", $r['harga_nasi']);
            $sheet->setCellValue("I{$row}", $r['total_cost']);

            if ($r['is_weekend']) {
                $sheet->getStyle("A{$row}:I{$row}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('F8D7DA');
            }

            $row++;
        }

        $lastDataRow = $row - 1;

        $sheet->mergeCells("A{$row}:H{$row}");
        $sheet->setCellValue("A{$row}", 'TOTAL');
        $sheet->getStyle("A{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
        $sheet->setCellValue("I{$row}", $grandTotal);
        $sheet->getStyle("A{$row}:I{$row}")->getFont()->setBold(true);
        $sheet->getStyle("A{$row}:I{$row}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('FFF3CD');

        $totalRow = $row;

        $sheet->getStyle("A{$headerRow}:I{$totalRow}")->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
        $sheet->getStyle("H{$headerRow}:I{$totalRow}")->getNumberFormat()->setFormatCode('"Rp" #,##0');
        $sheet->getStyle("C" . ($headerRow + 1) . ":G{$lastDataRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle("A" . ($headerRow + 1) . ":A{$lastDataRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        // Blok tanda tangan, mengikuti rekap_pdf.blade.php.
        $sigRow = $totalRow + 3;
        $sheet->mergeCells("F{$sigRow}:I{$sigRow}");
        $sheet->setCellValue("F{$sigRow}", 'Sukoharjo, ' . Carbon::now()->locale('id')->translatedFormat('d F Y'));
        $sheet->getStyle("F{$sigRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);

        $labelRow = $sigRow + 2;
        $sheet->mergeCells("A{$labelRow}:D{$labelRow}");
        $sheet->setCellValue("A{$labelRow}", 'Yang Mengajukan,');
        $sheet->mergeCells("F{$labelRow}:I{$labelRow}");
        $sheet->setCellValue("F{$labelRow}", 'Mengetahui,');
        $sheet->getStyle("A{$labelRow}:I{$labelRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        $nameRow = $labelRow + 5; // ruang kosong untuk tanda tangan basah
        $sheet->mergeCells("A{$nameRow}:D{$nameRow}");
        $sheet->setCellValue("A{$nameRow}", $userName);
        $sheet->mergeCells("F{$nameRow}:I{$nameRow}");
        $sheet->setCellValue("F{$nameRow}", 'ROSALIA WIWIEK WIDAWATI');
        $sheet->getStyle("A{$nameRow}:I{$nameRow}")->getFont()->setBold(true)->setUnderline(true);
        $sheet->getStyle("A{$nameRow}:I{$nameRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        $roleRow = $nameRow + 1;
        $sheet->mergeCells("A{$roleRow}:D{$roleRow}");
        $sheet->setCellValue("A{$roleRow}", 'Admin');
        $sheet->mergeCells("F{$roleRow}:I{$roleRow}");
        $sheet->setCellValue("F{$roleRow}", 'Admin Manager');
        $sheet->getStyle("A{$roleRow}:I{$roleRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        $colWidths = ['A' => 5, 'B' => 24, 'C' => 12, 'D' => 16, 'E' => 10, 'F' => 10, 'G' => 9, 'H' => 13, 'I' => 16];
        foreach ($colWidths as $col => $w) {
            $sheet->getColumnDimension($col)->setWidth($w);
        }

        $sheet->getRowDimension($headerRow)->setRowHeight(28);
    }

    /**
     * Tulis satu sheet detail (Normal Break / OS / Shift Siang / Shift Malam) dengan
     * kolom: No, NPK, Nama, Kantin, Tanggal, Waktu Scanning, Harga per Porsi, ditutup
     * dengan baris TOTAL PORSI di bagian paling bawah.
     */
    protected function writeDetailSheet(Spreadsheet $spreadsheet, string $title, Collection $rows): void
    {
        $sheet = $spreadsheet->createSheet();
        $sheet->setTitle($title);

        $headers = ['NO.', 'NPK', 'NAMA', 'KANTIN', 'TANGGAL', 'WAKTU SCANNING', 'HARGA PER PORSI'];
        $sheet->fromArray($headers, null, 'A1');
        $sheet->getStyle('A1:G1')->getFont()->setBold(true);
        $sheet->getStyle('A1:G1')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('F0F0F0');
        $sheet->getStyle('A1:G1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER);

        $row = 2;
        foreach ($rows->values() as $i => $r) {
            $sheet->setCellValue("A{$row}", $i + 1);
            $sheet->setCellValue("B{$row}", $r['npk']);
            $sheet->setCellValue("C{$row}", $r['name']);
            $sheet->setCellValue("D{$row}", $r['kantin']);
            $sheet->setCellValue("E{$row}", Carbon::parse($r['date'])->format('d-m-Y'));
            $sheet->setCellValue("F{$row}", $r['time'] ?? '-');
            $sheet->setCellValue("G{$row}", $r['cost']);
            $row++;
        }

        $lastDataRow = $row - 1;

        if ($rows->isEmpty()) {
            $sheet->mergeCells("A{$row}:G{$row}");
            $sheet->setCellValue("A{$row}", 'Tidak ada data.');
            $sheet->getStyle("A{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $row++;
        }

        // Baris TOTAL PORSI & TOTAL UANG di paling bawah.
        $totalPorsi = $rows->count();
        $totalUang  = $rows->sum('cost');

        $sheet->mergeCells("A{$row}:F{$row}");
        $sheet->setCellValue("A{$row}", 'TOTAL PORSI');
        $sheet->getStyle("A{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
        $sheet->setCellValue("G{$row}", $totalPorsi);
        $sheet->getStyle("A{$row}:G{$row}")->getFont()->setBold(true);
        $sheet->getStyle("A{$row}:G{$row}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('FFF3CD');
        $row++;

        $sheet->mergeCells("A{$row}:F{$row}");
        $sheet->setCellValue("A{$row}", 'TOTAL UANG');
        $sheet->getStyle("A{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
        $sheet->setCellValue("G{$row}", $totalUang);
        $sheet->getStyle("G{$row}")->getNumberFormat()->setFormatCode('"Rp" #,##0');
        $sheet->getStyle("A{$row}:G{$row}")->getFont()->setBold(true);
        $sheet->getStyle("A{$row}:G{$row}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('FFF3CD');

        $totalRow = $row;

        $sheet->getStyle("A1:G{$totalRow}")->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
        $sheet->getStyle("G2:G{$lastDataRow}")->getNumberFormat()->setFormatCode('"Rp" #,##0');
        $sheet->getStyle("A2:A{$lastDataRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle("B2:B{$lastDataRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle("E2:F{$lastDataRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle("G2:G{$lastDataRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);

        $colWidths = ['A' => 5, 'B' => 12, 'C' => 28, 'D' => 12, 'E' => 13, 'F' => 15, 'G' => 16];
        foreach ($colWidths as $col => $w) {
            $sheet->getColumnDimension($col)->setWidth($w);
        }
    }
}
