<?php

namespace App\Http\Controllers;

use App\Exports\RekapPresensiExport;
use App\Models\FaceAttendanceLog;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Facades\Excel;
use Yajra\DataTables\Facades\DataTables;

/**
 * Admin: log presensi wajah per scan (baris per fingerprint face + GPS detail),
 * difilter per tanggal. Dibedakan dari rekap:
 *   - rekap = 1 baris per karyawan per tanggal (Clock In/Out saja)
 *   - log   = 1 baris per transaksi scan (bisa banyak per tanggal)
 *
 * Sumber data utama: att_log (connection 'cii') JOIN BIODATA + BIODATA_KELUAR
 * untuk keterangan nama/NPK/dept, + face_attendance_logs untuk foto, koordinat,
 * confidence, status/liveness per scan.
 */
class FaceAttendanceLogController extends Controller
{
    /**
     * Halaman Log Face Attendance.
     */
    public function index(Request $request)
    {
        $onlyData = $request->boolean('only_data');
        $shouldLoad = $onlyData || $request->boolean('start') || $request->get('load') === '1';
        if (!$shouldLoad) {
            $shouldLoad = $request->filled('date') || $request->filled('date_from') || $request->filled('date_to')
                || $request->filled('q') || $request->filled('status') || $request->filled('mode')
                || $request->filled('department_id') || $request->filled('page');
        }

        $dateFrom = $this->resolveDate($request->get('date_from', $request->get('date')), Carbon::now()->startOfMonth()->toDateString());
        $dateTo = $this->resolveDate($request->get('date_to', $request->get('date')), Carbon::now()->toDateString());
        if ($dateFrom > $dateTo) {
            [$dateFrom, $dateTo] = [$dateTo, $dateFrom];
        }

        $search = trim((string) $request->get('q', ''));
        $status = trim((string) $request->get('status', ''));
        $mode = trim((string) $request->get('mode', ''));
        $department = trim((string) $request->get('department_id', ''));

        $departments = collect();
        try {
            $departments = DB::connection('cii')->table('DEPT')
                ->select('ID_DEPT', 'DEPARTEMENT')
                ->orderBy('DEPARTEMENT', 'asc')
                ->get();
        } catch (\Throwable $e) {
            Log::warning('Gagal memuat DEPT log: ' . $e->getMessage());
        }

        $logs = collect();
        $pagination = [
            'current_page' => 1,
            'per_page' => 50,
            'total' => 0,
            'total_pages' => 0,
            'has_prev' => false,
            'has_next' => false,
        ];
        $logError = null;
        $totalIn = 0;
        $totalOut = 0;
        $totalWajah = 0;
        $totalFinger = 0;

        if ($shouldLoad) {
            try {
                // Ambil SEMUA baris pada rentang filter untuk ringkasan kartu
                // (tanpa paging) supaya angka Clock In/Out/Wajah/Fingerprint
                // menunjukkan jumlah sebenarnya, bukan hanya 50 baris halaman.
                [$allLogs, , $summaryAll] = $this->buildLogs($dateFrom, $dateTo, $search, $status, $mode, $department, 1, 100000);
                $totalIn = $summaryAll['in'];
                $totalOut = $summaryAll['out'];
                $totalWajah = $summaryAll['wajah'];
                $totalFinger = $summaryAll['finger'];

                [$logs, $pagination, ] = $this->buildLogs($dateFrom, $dateTo, $search, $status, $mode, $department, (int) $request->get('page', 1), 50);
            } catch (\Throwable $e) {
                Log::error('Gagal memuat log face attendance: ' . $e->getMessage(), ['file' => $e->getFile() . ':' . $e->getLine()]);
                $logError = $e->getMessage();
            }
        }

        $view = $onlyData ? 'face_attendance.partials.log_data' : 'face_attendance.log_index';
        return view($view, compact(
            'logs', 'pagination', 'dateFrom', 'dateTo', 'search', 'status', 'mode',
            'department', 'departments', 'logError', 'totalIn', 'totalOut', 'totalWajah', 'totalFinger', 'shouldLoad'
        ));
    }

    /**
     * DataTables server-side untuk Log Face Attendance.
     *
     * PERUBAHAN 2026-11-09: log dialihkan ke DataTables server-side
     * (Yajra). Halaman hanya me-render shell + filter; baris dikirim
     * sebagai JSON via AJAX ke route log.data. Ini menggantikan
     * paginasi manual (only_data + fetch HTML) yang:
     *   - me-render ulang seluruh partial tiap klik halaman,
     *   - mengacaukan state DataTables client-side inisialisasi ulang,
     *   - dan tidak memberi sorting/searching server-side.
     *
     * Catatan kompatibilitas SQL Server:
     *   - buildLogs() tetap dipakai untuk baris (sudah teruji untuk
     *     join pin/baru + detail face). DataTables::of(collection)
     *     dipakai di sini, BUKAN DataTables::of(query), karena
     *     query dasarnya adalah joinSub cross-connection yang tidak
     *     bisa dibungkus Yajra sebagai subquery tanpa TOP/OFFSET.
     *   - Filter DataTables (search global + ordering kolom tanggal)
     *     diterapkan manual di atas collection sebelum make(true)
     *     supaya tetap berperilaku server-side dari sudut browser.
     */
    public function data(Request $request)
    {
        $dateFrom = $this->resolveDate($request->get('date_from', $request->get('date')), Carbon::now()->startOfMonth()->toDateString());
        $dateTo = $this->resolveDate($request->get('date_to', $request->get('date')), Carbon::now()->toDateString());
        if ($dateFrom > $dateTo) {
            [$dateFrom, $dateTo] = [$dateTo, $dateFrom];
        }
        $search = trim((string) $request->get('q', ''));
        $status = trim((string) $request->get('status', ''));
        $mode = trim((string) $request->get('mode', ''));
        $department = trim((string) $request->get('department_id', ''));

        // Tarik seluruh baris dalam rentang filter (tanpa paging manual);
        // paging + recordsTotal/recordFiltered dihitung Yajra dari collection.
        [$logs] = $this->buildLogs($dateFrom, $dateTo, $search, $status, $mode, $department, 1, 5000);

        // Normalisasi ke array agar Yajra bisa search/order kolom turunan.
        $rows = $logs->map(function ($l, $i) {
            $scan = $l->scan_date ? Carbon::parse($l->scan_date) : null;
            $confRaw = $l->confidence;
            $confText = '-';
            if ($confRaw !== null && $confRaw !== '' && (float) $confRaw > 1) {
                $confText = round((float) $confRaw, 2) . ' %';
            } elseif ($l->face_match !== null && $l->face_match !== '') {
                $confText = (int) $l->face_match === 1 ? 'Match' : 'Tidak match';
            }

            return [
                'DT_RowIndex' => $i + 1,
                'scan_date_sort' => $scan ? $scan->format('Y-m-d H:i:s') : '',
                'tanggal' => $scan ? $scan->format('Y-m-d') : '-',
                'waktu' => $scan ? $scan->format('H:i:s') : '-',
                'karyawan' => $l->name ?? '-',
                'npk_barcode' => trim((string) ($l->npk ?? '-')) . ' / ' . ($l->pin ?? '-'),
                'departemen' => $l->dept ?? '-',
                'mode_label' => $l->mode_label ?? '-',
                'action_label' => $l->action_label ?? '-',
                'verifymode' => $l->verifymode ?? '-',
                'gps' => $this->gpsLabel($l),
                'face_image' => $l->face_image ?? null,
                'confidence' => $confText,
                'status' => $l->status ?? '-',
                'ip_address' => $l->ip_address ?? '-',
                // metadata untuk preview foto (tidak ditampilkan sebagai kolom)
                'preview_name' => $l->name ?? '-',
                'preview_npk' => $l->npk ?? '-',
                'is_keluar' => !empty($l->is_keluar),
            ];
        })->values();

        return DataTables::of($rows)
            ->addColumn('karyawan_html', function ($row) {
                $badge = !empty($row['is_keluar']) ? ' <span class="badge badge-warning ml-1">Keluar</span>' : '';
                return '<strong>' . e($row['karyawan']) . '</strong>' . $badge;
            })
            ->addColumn('mode_html', function ($row) {
                return ($row['mode_label'] === 'Wajah')
                    ? '<span class="badge badge-primary">Wajah</span>'
                    : '<span class="badge badge-info">Fingerprint</span>';
            })
            ->addColumn('gps_html', function ($row) {
                return e($row['gps']);
            })
            ->addColumn('foto_html', function ($row) {
                $src = $this->safeFaceImageSrc($row['face_image'] ?? '');
                if ($src === null) {
                    return '<span class="text-muted">-</span>';
                }
                return '<a href="javascript:void(0)" class="btn-preview-face d-inline-block mx-auto" style="text-align:center;" data-img="' . e($src) . '" data-name="' . e($row['preview_name']) . '" data-npk="' . e($row['preview_npk']) . '">'
                    . '<img data-src="' . e($src) . '" alt="Foto wajah" class="face-thumb rounded border shadow-sm d-block mx-auto" style="width:48px;height:48px;object-fit:cover;" loading="lazy">'
                    . '</a>';
            })
            ->addColumn('confidence_html', function ($row) {
                $c = $row['confidence'];
                if ($c === 'Match') return '<span class="badge badge-success">Match</span>';
                if ($c === 'Tidak match') return '<span class="badge badge-danger">Tidak match</span>';
                return e($c);
            })
            ->addColumn('status_html', function ($row) {
                $s = trim((string) $row['status']);
                return $s !== '' && $s !== '-' ? '<span class="badge badge-secondary">' . e($s) . '</span>' : '<span class="text-muted">-</span>';
            })
            ->rawColumns(['karyawan_html', 'mode_html', 'gps_html', 'foto_html', 'confidence_html', 'status_html'])
            ->make(true);
    }

    public function export(Request $request)
    {
        $dateFrom = $this->resolveDate($request->get('date_from', $request->get('date')), Carbon::now()->startOfMonth()->toDateString());
        $dateTo = $this->resolveDate($request->get('date_to', $request->get('date')), Carbon::now()->toDateString());
        if ($dateFrom > $dateTo) {
            [$dateFrom, $dateTo] = [$dateTo, $dateFrom];
        }
        $search = trim((string) $request->get('q', ''));
        $status = trim((string) $request->get('status', ''));
        $mode = trim((string) $request->get('mode', ''));
        $department = trim((string) $request->get('department_id', ''));

        [$logs] = $this->buildLogs($dateFrom, $dateTo, $search, $status, $mode, $department, 1, 5000);

        $rows = $logs->map(function ($l, $i) {
            return [
                'No' => $i + 1,
                'Tanggal' => $l->scan_date ? Carbon::parse($l->scan_date)->format('Y-m-d') : '-',
                'Waktu' => $l->scan_date ? Carbon::parse($l->scan_date)->format('H:i:s') : '-',
                'Nama' => $l->name ?? '-',
                'NPK' => $l->npk ?? '-',
                'Departemen' => $l->dept ?? '-',
                'Mode' => $l->mode_label ?? '-',
                'Aksi' => $l->action_label ?? '-',
                'Status GPS' => $l->inside_polygon !== null && $l->inside_polygon !== '' ? ((int) $l->inside_polygon === 1 ? 'Di dalam area' : 'Di luar area') : '-',
                'Status Face' => $l->face_match !== null && $l->face_match !== '' ? ((int) $l->face_match === 1 ? 'Match' : 'Tidak match') : '-',
                // Kolom confidence di DB decimal(5,0) hanya 0/1, jadi
                // persen ditampilkan hanya bila nilai mentah > 1 (persen asli).
                'Confidence' => $l->confidence !== null && $l->confidence !== '' && (float) $l->confidence > 1 ? round((float) $l->confidence, 2) . ' %' : ($l->face_match !== null && $l->face_match !== '' ? ((int) $l->face_match === 1 ? 'Match' : 'Tidak match') : '-'),
                'Status' => $l->status ?? '-',
                'IP Address' => $l->ip_address ?? '-',
            ];
        })->toArray();

        return Excel::download(new RekapPresensiExport($rows, "Log Face {$dateFrom} s/d {$dateTo}", 'Semua Departemen', $search ?: '-'), "log-face-attendance-{$dateFrom}-{$dateTo}.xlsx");
    }

    private function buildLogs(string $dateFrom, string $dateTo, string $search, string $status, string $mode, string $department, int $page, int $perPage): array
    {
        $barcodeExpr = "LTRIM(RTRIM(CONVERT(varchar(64), %s.BARCODE)))";

        $masterQuery = DB::connection('cii')->table('BIODATA as b')
            ->leftJoin('DEPT as d', 'b.ID_DEPT', '=', 'd.ID_DEPT')
            ->selectRaw(sprintf($barcodeExpr, 'b') . ' AS BARCODE')
            ->select('b.NPK', 'b.NAMA_KARYAWAN', 'b.BARCODE', DB::raw('d.DEPARTEMENT as DEPT'), DB::raw('0 as IS_KELUAR'));
        if ($department !== '') {
            $masterQuery->where('b.ID_DEPT', $department);
        }
        if ($search !== '') {
            $masterQuery->where(function ($q) use ($search) {
                $q->where('b.NAMA_KARYAWAN', 'like', '%' . $search . '%')
                  ->orWhere('b.NPK', 'like', '%' . $search . '%')
                  ->orWhere('b.BARCODE', 'like', '%' . $search . '%');
            });
        }
        $masterActive = $masterQuery->get();

        $keluarQuery = DB::connection('cii')->table('BIODATA_KELUAR as bk')
            ->leftJoin('DEPT as dk', 'bk.ID_DEPT', '=', 'dk.ID_DEPT')
            ->selectRaw(sprintf($barcodeExpr, 'bk') . ' AS BARCODE')
            ->select('bk.NPK', 'bk.NAMA_KARYAWAN', 'bk.BARCODE', DB::raw('dk.DEPARTEMENT as DEPT'), DB::raw('1 as IS_KELUAR'));
        if ($department !== '') {
            $keluarQuery->where('bk.ID_DEPT', $department);
        }
        if ($search !== '') {
            $keluarQuery->where(function ($q) use ($search) {
                $q->where('bk.NAMA_KARYAWAN', 'like', '%' . $search . '%')
                  ->orWhere('bk.NPK', 'like', '%' . $search . '%')
                  ->orWhere('bk.BARCODE', 'like', '%' . $search . '%');
            });
        }
        $masterKeluar = $keluarQuery->get();

        // Gabungkan master: aktif menang untuk barcode duplikat
        $byBarcode = [];
        foreach ($masterKeluar as $k) {
            $byBarcode[$this->normalizePin($k->BARCODE)] = $k;
        }
        foreach ($masterActive as $a) {
            $byBarcode[$this->normalizePin($a->BARCODE)] = $a;
        }
        $barcodes = array_keys(array_filter($byBarcode, fn ($k) => $k !== ''));

        if (empty($barcodes)) {
            return [collect(), ['current_page' => $page, 'per_page' => $perPage, 'total' => 0, 'total_pages' => 0, 'has_prev' => false, 'has_next' => false], ['in' => 0, 'out' => 0, 'wajah' => 0, 'finger' => 0]];
        }

        // att_log per scan pada rentang tanggal. Kalau ada pencarian (q),
        // longgarkan join master: cocokkan juga langsung ke a.pin supaya
        // pencarian nama/NPK/barcode/pin tetap mengembalikan barisnya.
        $pinSub = DB::connection('cii')->table('BIODATA as b')->selectRaw(sprintf($barcodeExpr, 'b') . ' AS BARCODE');
        if ($department !== '') {
            $pinSub->where('b.ID_DEPT', $department);
        }
        if ($search !== '') {
            $pinSub->where(function ($q) use ($search) {
                $q->where('b.NAMA_KARYAWAN', 'like', '%' . $search . '%')
                  ->orWhere('b.NPK', 'like', '%' . $search . '%')
                  ->orWhere('b.BARCODE', 'like', '%' . $search . '%');
            });
        }
        $pinSubKeluar = DB::connection('cii')->table('BIODATA_KELUAR as bk')->selectRaw(sprintf($barcodeExpr, 'bk') . ' AS BARCODE');
        if ($department !== '') {
            $pinSubKeluar->where('bk.ID_DEPT', $department);
        }
        if ($search !== '') {
            $pinSubKeluar->where(function ($q) use ($search) {
                $q->where('bk.NAMA_KARYAWAN', 'like', '%' . $search . '%')
                  ->orWhere('bk.NPK', 'like', '%' . $search . '%')
                  ->orWhere('bk.BARCODE', 'like', '%' . $search . '%');
            });
        }
        $pinUnion = $pinSub->union($pinSubKeluar);

        $pinExpr = DB::raw('LTRIM(RTRIM(a.pin))');

        $base = DB::connection('cii')->table('att_log as a')
            ->leftJoinSub($pinUnion, 'bio', function ($join) use ($pinExpr) {
                $join->on('bio.BARCODE', '=', $pinExpr);
            })
            ->whereBetween('a.scan_date', [$dateFrom . ' 00:00:00', $dateTo . ' 23:59:59'])
            ->select([
                'a.pin', 'a.scan_date', 'a.verifymode', 'a.inoutmode', 'a.sn', 'a.att_id',
            ]);

        // Kalau pencarian aktif, terima baris yang lolos join master ATAU
        // yang pin-nya sendiri cocok dengan kata kunci (pin/dept via master
        // tidak ikut di sini — dept tetap difilter lewat subquery pin).
        if ($search !== '') {
            $base->where(function ($q) use ($search, $pinExpr) {
                $q->where('bio.BARCODE', 'is not null')
                  ->orWhere('a.pin', 'like', '%' . $search . '%');
            });
        }

        // Mode filter P: wajah vs fingerprint
        if ($mode === 'wajah') {
            $base->where(function ($q) {
                $q->where('a.sn', 'like', 'FACE%')
                  ->orWhere('a.sn', 'like', '%FACE_GPS%')
                  ->orWhere('a.verifymode', 15);
            });
        } elseif ($mode === 'fingerprint') {
            $base->where(function ($q) {
                $q->where('a.sn', 'not like', 'FACE%')
                  ->where('a.sn', 'not like', '%FACE_GPS%')
                  ->where('a.verifymode', '<>', 15);
            });
        }

        // Filter cari cepat (q) juga dicocokkan ke pin mentah di att_log,
        // supaya pencarian berdasarkan PIN/Barcode perangkat tetap jalan
        // walau pin belum terdaftar di master BIODATA.
        // ( sudah ditangani di atas via leftJoinSub + where bio.BARCODE / a.pin )

        $total = (clone $base)->count();

        $rows = (clone $base)
            ->orderBy('a.scan_date', 'desc')
            ->offset(($page - 1) * $perPage)
            ->limit($perPage)
            ->get();

        // Detail face per att_id (foto+GPS+confidence+status) dalam satu query
        $attIds = $rows->pluck('att_id')->filter()->unique()->values()->all();
        $faceByAttId = [];
        if (!empty($attIds)) {
            try {
                $faceRows = DB::table('face_attendance_logs')
                    ->whereIn('att_id', $attIds)
                    ->orderBy('scan_date', 'asc')
                    ->get(['att_id', 'scan_date', 'action', 'face_image', 'latitude', 'longitude', 'accuracy', 'inside_polygon', 'work_location_id', 'face_match', 'confidence', 'liveness_passed', 'status', 'rejection_reason', 'ip_address', 'user_agent']);
                foreach ($faceRows as $fr) {
                    $faceByAttId[(string) $fr->att_id] = $fr;
                }
            } catch (\Throwable $e) {
                Log::warning('Gagal memuat detail face attendance logs: ' . $e->getMessage());
            }
        }

        $logs = collect();
        $sumIn = 0; $sumOut = 0; $sumWajah = 0; $sumFinger = 0;
        foreach ($rows as $r) {
            $pinKey = $this->normalizePin($r->pin);
            $master = $byBarcode[$pinKey] ?? null;
            $face = $faceByAttId[(string) ($r->att_id ?? '')] ?? null;

            $modeLabel = $this->modeLabel($r, $face);
            if ($modeLabel === 'Wajah') { $sumWajah++; } else { $sumFinger++; }

            $actionLabel = $this->actionLabel($r, $face);
            if (stripos($actionLabel, 'In') !== false || $actionLabel === 'IN') { $sumIn++; } else { $sumOut++; }

            if ($status !== '') {
                $st = strtolower((string) ($face->status ?? ''));
                if ($status === 'success' && !in_array($st, ['success', 'berhasil', 'accepted', 'ok'], true)) { continue; }
                if ($status === 'failed' && !(in_array($st, ['failed', 'gagal', 'rejected', 'error'], true) || !empty($face->rejection_reason ?? ''))) { continue; }
                if ($status === 'ditolak' && empty($face->rejection_reason ?? '')) { continue; }
            }

            $logs->push((object) [
                'pin' => $r->pin,
                'scan_date' => $r->scan_date,
                'npk' => $master->NPK ?? '-',
                'name' => $master->NAMA_KARYAWAN ?? ($pinKey ?: '-'),
                'dept' => $master->DEPT ?: '-',
                'is_keluar' => (int) ($master->IS_KELUAR ?? 0) === 1,
                'mode_label' => $modeLabel,
                'verifymode' => $r->verifymode,
                'sn' => $r->sn,
                'att_id' => $r->att_id,
                'inoutmode' => $r->inoutmode,
                'action_label' => $actionLabel,
                'face_image' => $face->face_image ?? null,
                'latitude' => $face->latitude ?? null,
                'longitude' => $face->longitude ?? null,
                'accuracy' => $face->accuracy ?? null,
                'inside_polygon' => $face->inside_polygon ?? null,
                'work_location_id' => $face->work_location_id ?? null,
                'face_match' => $face->face_match ?? null,
                'confidence' => $face->confidence ?? null,
                'liveness_passed' => $face->liveness_passed ?? null,
                'status' => $face->status ?? null,
                'rejection_reason' => $face->rejection_reason ?? null,
                'ip_address' => $face->ip_address ?? null,
                'user_agent' => $face->user_agent ?? null,
            ]);
        }

        $totalPages = $total > 0 ? (int) ceil($total / $perPage) : 0;
        $pagination = [
            'current_page' => $page,
            'per_page' => $perPage,
            'total' => $total,
            'total_pages' => $totalPages,
            'has_prev' => $page > 1,
            'has_next' => $page < $totalPages,
        ];

        return [$logs->values(), $pagination, ['in' => $sumIn, 'out' => $sumOut, 'wajah' => $sumWajah, 'finger' => $sumFinger]];
    }

    private function gpsLabel($l): string
    {
        $parts = [];
        if ($l->inside_polygon !== null && $l->inside_polygon !== '') {
            $parts[] = (int) $l->inside_polygon === 1 ? 'Di dalam' : 'Di luar';
        }
        if ($l->latitude !== null && $l->longitude !== null) {
            $parts[] = number_format((float) $l->latitude, 6) . ', ' . number_format((float) $l->longitude, 6);
        }
        return $parts ? implode(' · ', $parts) : '-';
    }

    /**
     * Sanitasi base64 foto wajah (sama dengan partial v3):
     * hanya data:image/...;base64 yang valid atau base64 murni
     * yang decode bersih. Nilai non-base64 (mis. teks "Foto")
     * mengembalikan null -> tampil "-" dan TIDAK jadi broken image.
     */
    private function safeFaceImageSrc($raw): ?string
    {
        $raw = trim((string) ($raw ?? ''));
        if ($raw === '') {
            return null;
        }
        if (str_starts_with($raw, 'data:')) {
            return preg_match('#^data:image/(jpeg|jpg|png|gif|webp);base64,#i', $raw) ? $raw : null;
        }
        if (preg_match('/^[A-Za-z0-9+\/=_-]+$/', $raw) && strlen($raw) >= 100) {
            $urlSafe = strtr($raw, '-_', '+/');
            $pad = strlen($urlSafe) % 4;
            if ($pad > 0) {
                $urlSafe .= str_repeat('=', 4 - $pad);
            }
            if (@base64_decode($urlSafe, true) !== false) {
                return 'data:image/jpeg;base64,' . $raw;
            }
        }
        return null;
    }

    private function modeLabel($log, $face = null): string
    {
        // Baris yang punya detail di face_attendance_logs selalu wajah
        if ($face !== null) {
            return 'Wajah';
        }
        $sn = strtoupper(trim((string) ($log->sn ?? '')));
        if ($sn === 'FACE_GPS_WEB' || $sn === 'FACE_GPS' || strpos($sn, 'FACE') === 0 || strpos($sn, 'GPS') !== false) {
            return 'Wajah';
        }
        if ((int) ($log->verifymode ?? -1) === 15) {
            return 'Wajah';
        }
        return 'Fingerprint';
    }

    private function actionLabel($log, $face = null): string
    {
        if ($face && !empty($face->action)) {
            $up = strtoupper((string) $face->action);
            if (in_array($up, ['IN', 'CLOCK IN', 'MASUK', 'I', '0'], true)) { return 'Clock In'; }
            if (in_array($up, ['OUT', 'CLOCK OUT', 'PULANG', 'O', '1'], true)) { return 'Clock Out'; }
            return $face->action;
        }
        $inout = $log->inoutmode ?? null;
        if ($inout !== null && $inout !== '') {
            return ((int) $inout) === 0 ? 'Clock In' : 'Clock Out';
        }
        return '-';
    }

    private function normalizePin($value): string
    {
        if ($value === null) {
            return '';
        }
        $pin = trim((string) $value);
        if ($pin !== '' && substr($pin, -2) === '.0' && ctype_digit(substr($pin, 0, -2))) {
            $pin = substr($pin, 0, -2);
        }
        return $pin;
    }

    private function resolveDate($value, string $default): string
    {
        $value = trim((string) $value);
        if ($value === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return $default;
        }
        return $value;
    }
}
