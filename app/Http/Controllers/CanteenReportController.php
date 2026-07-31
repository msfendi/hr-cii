<?php

namespace App\Http\Controllers;

use App\Models\CanteenReport;
use App\Models\CanteenTwoReport;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Contracts\View\View;

class CanteenReportController extends Controller
{
    /** Jam mulai window istirahat utama. */
    protected string $mainBreakStart = '11:00:00';

    /** Jam selesai window istirahat utama. */
    protected string $mainBreakEnd = '13:30:00';

    /** Biaya per karyawan per makan (Rp). */
    protected int $costPerMeal = 7000;

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
            if ($npksForDept !== null) {
                $q1->whereIn('npk', $npksForDept);
            }
            $rows1 = $q1->get()->map(fn($r) => $this->mapRow($r, 'Kantin 1'));
        }

        if ($kantin !== 'Kantin 1') {
            $q2 = CanteenTwoReport::whereBetween('date', [$start, $end]);
            if ($npksForDept !== null) {
                $q2->whereIn('npk', $npksForDept);
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

        $biodataAll = DB::connection('cii')
            ->table('BIODATA')
            ->select('NPK', 'ID_DEPT')
            ->union(
                DB::connection('cii')->table('BIODATA_KELUAR')->select('NPK', 'ID_DEPT')
            );

        return DB::connection('cii')
            ->table(DB::raw('(' . $biodataAll->toSql() . ') AS biodata_all'))
            ->mergeBindings($biodataAll)
            ->whereIn('biodata_all.ID_DEPT', $deptIds)
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

        // Chunk untuk menghindari batas jumlah parameter whereIn di SQL Server.
        foreach ($npks->chunk(1000) as $chunk) {
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
                ->whereIn('biodata_all.NPK', $chunk->all())
                ->select('biodata_all.NPK', 'DEPT.DEPARTEMENT')
                ->get();

            foreach ($rows as $row) {
                $map[$row->NPK] = $row->DEPARTEMENT;
            }
        }

        return $map;
    }
}
