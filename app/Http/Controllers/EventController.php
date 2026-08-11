<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\EventInvitation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class EventController extends Controller
{
    /**
     * Halaman manajemen event: list semua event + form tambah/edit +
     * tombol "Aktifkan". Setiap event tinggal dipilih blade folder-nya
     * dari daftar folder yang sudah ada di resources/views/event_invitation.
     */
    public function index(): View
    {
        $events       = Event::orderByDesc('tanggal_event')->get();
        $bladeFolders = $this->availableBladeFolders();

        return view('event_invitation.admin.index', compact('events', 'bladeFolders'));
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $this->validated($request);
        $event     = Event::create($validated);

        if ($request->boolean('is_active')) {
            $this->activateOnly($event);
        }

        return response()->json([
            'status'  => 'success',
            'message' => "Event \"{$event->nama_event}\" berhasil ditambahkan.",
            'data'    => $event,
        ]);
    }

    public function update(Request $request, Event $event): JsonResponse
    {
        $validated = $this->validated($request);
        $event->update($validated);

        if ($request->boolean('is_active')) {
            $this->activateOnly($event);
        }

        return response()->json([
            'status'  => 'success',
            'message' => "Event \"{$event->nama_event}\" berhasil diperbarui.",
            'data'    => $event->fresh(),
        ]);
    }

    public function destroy(Event $event): JsonResponse
    {
        $nama = $event->nama_event;
        $event->delete(); // event_invitations ikut terhapus (cascadeOnDelete)

        return response()->json([
            'status'  => 'success',
            'message' => "Event \"{$nama}\" berhasil dihapus.",
        ]);
    }

    /**
     * Jadikan $event satu-satunya event yang aktif (nonaktifkan semua
     * event lain). Ini yang dipakai supaya kalau ada event serupa lagi
     * di masa depan, tinggal buat baris baru lalu klik "Aktifkan".
     */
    public function activate(Event $event): JsonResponse
    {
        $this->activateOnly($event);

        return response()->json([
            'status'  => 'success',
            'message' => "Event \"{$event->nama_event}\" sekarang aktif dan dipakai di halaman scan/RSVP publik.",
        ]);
    }

    /**
     * Detail peserta (hadir & tidak hadir) untuk satu event, dipakai oleh
     * modal "Detail Peserta" di halaman admin. Sekaligus mengembalikan
     * rekap per departemen untuk chart persentase kehadiran.
     */
    public function peserta(Event $event): JsonResponse
    {
        $peserta = EventInvitation::where('event_id', $event->id)
            ->orderByDesc('responded_at')
            ->get(['id', 'npk', 'nama', 'status', 'ucapan', 'responded_at']);

        // Departemen tidak diambil dari kolom event_invitations.departemen,
        // tapi dari BIODATA/BIODATA_KELUAR (cii) di-join ke DEPT.
        $departmentMap = $this->getDepartmentMapForNpks(
            $peserta->pluck('npk')->filter()->unique()->values()->all()
        );

        $peserta = $peserta
            ->map(function ($p) use ($departmentMap) {
                $p->departemen = $departmentMap[$p->npk] ?? '(Tanpa Departemen)';

                return $p;
            })
            ->values();

        $perDepartemen = $peserta
            ->groupBy('departemen')
            ->map(function ($group, $departemen) {
                $hadir      = $group->where('status', 'hadir')->count();
                $tidakHadir = $group->where('status', 'tidak_hadir')->count();
                $total      = $group->count();

                return (object) [
                    'departemen'         => $departemen,
                    'hadir'              => $hadir,
                    'tidak_hadir'        => $tidakHadir,
                    'total'              => $total,
                    'persen_hadir'       => $total > 0 ? round($hadir / $total * 100, 1) : 0,
                    'persen_tidak_hadir' => $total > 0 ? round($tidakHadir / $total * 100, 1) : 0,
                ];
            })
            ->sortKeys()
            ->values();

        return response()->json([
            'status' => 'success',
            'event' => [
                'id'         => $event->id,
                'nama_event' => $event->nama_event,
            ],
            'peserta'        => $peserta,
            'per_departemen' => $perDepartemen,
        ]);
    }

    /**
     * Export data peserta (ringkasan per departemen + detail per orang)
     * ke Excel, dipakai oleh tombol "Export Excel" di modal Detail Peserta.
     */
    public function exportPeserta(Event $event)
    {
        $peserta = EventInvitation::where('event_id', $event->id)
            ->orderByDesc('responded_at')
            ->get();

        // Departemen tidak diambil dari kolom event_invitations.departemen,
        // tapi dari BIODATA/BIODATA_KELUAR (cii) di-join ke DEPT.
        $departmentMap = $this->getDepartmentMapForNpks(
            $peserta->pluck('npk')->filter()->unique()->values()->all()
        );

        $peserta = $peserta
            ->map(function ($p) use ($departmentMap) {
                $p->departemen = $departmentMap[$p->npk] ?? '(Tanpa Departemen)';

                return $p;
            })
            ->values();

        $perDepartemen = $peserta
            ->groupBy('departemen')
            ->map(function ($group, $departemen) {
                return (object) [
                    'departemen'  => $departemen,
                    'hadir'       => $group->where('status', 'hadir')->count(),
                    'tidak_hadir' => $group->where('status', 'tidak_hadir')->count(),
                    'total'       => $group->count(),
                ];
            })
            ->sortKeys()
            ->values();

        $spreadsheet = new Spreadsheet();

        // --- Sheet 1: Ringkasan per Departemen ---
        $summary = $spreadsheet->getActiveSheet();
        $summary->setTitle('Ringkasan');
        $summary->fromArray(
            ['Departemen', 'Hadir', 'Tidak Hadir', 'Total', '% Hadir', '% Tidak Hadir'],
            null,
            'A1'
        );

        $row = 2;
        foreach ($perDepartemen as $d) {
            $persenHadir = $d->total > 0 ? round($d->hadir / $d->total * 100, 1) : 0;
            $persenTidak = $d->total > 0 ? round($d->tidak_hadir / $d->total * 100, 1) : 0;

            $summary->fromArray([
                $d->departemen,
                $d->hadir,
                $d->tidak_hadir,
                $d->total,
                $persenHadir . '%',
                $persenTidak . '%',
            ], null, "A{$row}");

            $row++;
        }

        $totalHadir = $perDepartemen->sum('hadir');
        $totalTidak = $perDepartemen->sum('tidak_hadir');
        $totalAll   = $perDepartemen->sum('total');

        $summary->fromArray([
            'TOTAL',
            $totalHadir,
            $totalTidak,
            $totalAll,
            $totalAll > 0 ? round($totalHadir / $totalAll * 100, 1) . '%' : '0%',
            $totalAll > 0 ? round($totalTidak / $totalAll * 100, 1) . '%' : '0%',
        ], null, "A{$row}");

        $summary->getStyle('A1:F1')->getFont()->setBold(true);
        $summary->getStyle("A{$row}:F{$row}")->getFont()->setBold(true);
        foreach (range('A', 'F') as $col) {
            $summary->getColumnDimension($col)->setAutoSize(true);
        }

        // --- Sheet 2: Detail Peserta ---
        $detail = $spreadsheet->createSheet();
        $detail->setTitle('Detail Peserta');
        $detail->fromArray(
            ['NPK', 'Nama', 'Departemen', 'Status', 'Ucapan', 'Waktu Respon'],
            null,
            'A1'
        );

        $row = 2;
        foreach ($peserta as $p) {
            $detail->fromArray([
                $p->npk,
                $p->nama,
                $p->departemen,
                $p->status === 'hadir' ? 'Hadir' : 'Tidak Hadir',
                $p->ucapan,
                optional($p->responded_at)->format('d-m-Y H:i'),
            ], null, "A{$row}");

            $row++;
        }

        $detail->getStyle('A1:F1')->getFont()->setBold(true);
        foreach (range('A', 'F') as $col) {
            $detail->getColumnDimension($col)->setAutoSize(true);
        }

        $spreadsheet->setActiveSheetIndex(0);

        $safeName = preg_replace('/[^A-Za-z0-9_\-]/', '_', $event->nama_event);
        $fileName = "Peserta_{$safeName}_" . now()->format('Ymd_His') . '.xlsx';

        $writer    = new Xlsx($spreadsheet);
        $tempFile  = tempnam(sys_get_temp_dir(), 'peserta_export');
        $writer->save($tempFile);

        return response()->download($tempFile, $fileName)->deleteFileAfterSend(true);
    }

    /* ------------------------------------------------------------ */
    /*  Helpers                                                        */
    /* ------------------------------------------------------------ */

    /**
     * Ambil nama departemen per NPK dari data HR (koneksi `cii`), bukan
     * dari kolom event_invitations.departemen. Sumbernya union BIODATA
     * (karyawan aktif) & BIODATA_KELUAR (karyawan keluar), masing-masing
     * di-join ke DEPT lewat ID_DEPT untuk dapat nama departemennya.
     *
     * Di-chunk per 1500 NPK untuk menghindari limit 2100 parameter SQL Server.
     *
     * @return array<string, string> NPK => nama departemen
     */
    private function getDepartmentMapForNpks(array $npks): array
    {
        if (empty($npks)) {
            return [];
        }

        $map = [];

        foreach (array_chunk($npks, 1500) as $chunk) {
            $rows = DB::connection('cii')
                ->table('BIODATA')
                ->join('DEPT', 'DEPT.ID_DEPT', '=', 'BIODATA.ID_DEPT')
                ->whereIn('BIODATA.NPK', $chunk)
                ->select('BIODATA.NPK as npk', 'DEPT.DEPARTEMENT as departemen')
                ->union(
                    DB::connection('cii')
                        ->table('BIODATA_KELUAR')
                        ->join('DEPT', 'DEPT.ID_DEPT', '=', 'BIODATA_KELUAR.ID_DEPT')
                        ->whereIn('BIODATA_KELUAR.NPK', $chunk)
                        ->select('BIODATA_KELUAR.NPK as npk', 'DEPT.DEPARTEMENT as departemen')
                )
                ->get();

            foreach ($rows as $row) {
                $map[$row->npk] = $row->departemen;
            }
        }

        return $map;
    }

    private function activateOnly(Event $event): void
    {
        Event::query()->where('id', '!=', $event->id)->update(['is_active' => false]);
        $event->update(['is_active' => true]);
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'nama_event'    => 'required|string|max:150',
            'tanggal_event' => 'required|date',
            'waktu_event'   => 'required|string|max:100',
            'lokasi_event'  => 'required|string|max:255',
            'dress_code'    => 'nullable|string|max:150',
            'detail_event'  => 'nullable|string|max:2000',
            'view_folder'   => 'required|string|max:100|alpha_dash',
        ]);
    }

    /**
     * Scan resources/views/event_invitation/* untuk dijadikan pilihan
     * "Pakai Blade Yang Mana" di form admin (folder "admin" sendiri
     * dikecualikan karena itu bukan template event).
     */
    private function availableBladeFolders(): array
    {
        $base = resource_path('views/event_invitation');

        if (!is_dir($base)) {
            return [];
        }

        return collect(scandir($base))
            ->filter(fn($folder) => !in_array($folder, ['.', '..', 'admin']) && is_dir($base . '/' . $folder))
            ->values()
            ->all();
    }
}
