<?php

namespace App\Http\Controllers;

use App\Exports\WageRevision\WageRevisionResultExport;
use App\Exports\WageRevision\WageRevisionTemplateExport;
use App\Imports\WageRevisionItemImport;
use App\Models\WageRevisionBatch;
use App\Models\WageRevisionItem;
use App\Models\WageRevisionRule;
use App\Services\PayrollRoleFilterService;
use App\Services\WageRevisionApplier;
use App\Services\WageRevisionBuilder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Maatwebsite\Excel\Facades\Excel;
use RealRashid\SweetAlert\Facades\Alert;
use Throwable;

/**
 * Penyesuaian upah tahunan: non-staff naik dengan satu persentase seragam, staff/expat
 * diketik nominal gaji barunya oleh HRD. Tidak ada perhitungan payroll yang diubah.
 */
class WageRevisionController extends Controller
{
    public function __construct(
        private WageRevisionBuilder $builder,
        private WageRevisionApplier $applier,
    ) {
    }

    private function isAdmin(): bool
    {
        return (bool) Auth::user()?->hasRole('Admin');
    }

    private function role(): ?string
    {
        return PayrollRoleFilterService::getRole(Auth::user());
    }

    public function index()
    {
        $this->authorize('viewAny', WageRevisionBatch::class);

        $batches = WageRevisionBatch::orderByDesc('year')->orderByDesc('id')->get();

        return view('wage_revisions.index', [
            'batches'  => $batches,
            'canCreate' => $this->isAdmin() || PayrollRoleFilterService::isRegistered($this->role()),
        ]);
    }

    public function store(Request $request)
    {
        $this->authorize('create', WageRevisionBatch::class);

        $data = $request->validate([
            'year'           => ['required', 'integer', 'min:2020', 'max:2100'],
            'effective_date' => ['required', 'date'],
            'umr_new'        => ['required', 'numeric', 'min:0'],
            'percent'        => ['required', 'numeric', 'min:0', 'max:100'],
            'round_to'       => ['required', 'integer', 'in:1,10,50,100,500,1000'],
            'note'           => ['nullable', 'string', 'max:255'],
        ], [], [
            'year'           => 'tahun',
            'effective_date' => 'tanggal efektif',
            'umr_new'        => 'UMR baru',
            'percent'        => 'persentase kenaikan non-staff',
            'round_to'       => 'pembulatan',
        ]);

        $data['effective_date'] = \Carbon\Carbon::parse($data['effective_date'])->startOfMonth()->toDateString();

        $existing = WageRevisionBatch::where('year', $data['year'])->first();

        if ($existing) {
            Alert::error('Gagal', 'Batch tahun ' . $data['year'] . ' sudah ada.');

            return redirect()->route('wage-revisions.show', $existing->id);
        }

        $batch = WageRevisionBatch::create([
            'year'           => $data['year'],
            'umr_new'        => $data['umr_new'],
            'effective_date' => $data['effective_date'],
            'status'         => WageRevisionBatch::STATUS_DRAFT,
            'note'           => $data['note'] ?? null,
            'created_by'     => Auth::id(),
        ]);

        // Satu rule per segmen: non-staff persen seragam, staff/expat nominal per orang.
        $batch->rules()->createMany([
            [
                'segment'        => WageRevisionItem::SEGMENT_NON_STAFF,
                'mode'           => WageRevisionRule::MODE_PERCENT,
                'percent'        => $data['percent'],
                'round_to'       => $data['round_to'],
                'warn_below_umr' => true,
            ],
            [
                'segment'        => WageRevisionItem::SEGMENT_STAFF_EXPAT,
                'mode'           => WageRevisionRule::MODE_NOMINAL,
                'percent'        => null,
                'round_to'       => 100,
                'warn_below_umr' => true,
            ],
        ]);

        Alert::success('Berhasil', 'Batch penyesuaian upah ' . $batch->year . ' dibuat.');

        return redirect()->route('wage-revisions.show', $batch->id);
    }

    public function show(WageRevisionBatch $batch)
    {
        $this->authorize('view', $batch);

        $items = $batch->items()->orderBy('segment')->orderBy('npk')->get();

        return view('wage_revisions.show', [
            'batch'         => $batch,
            'items'         => $items,
            'stats'         => $this->stats($batch),
            'payrollLocked' => $this->applier->payrollRunExistsFor(
                optional($batch->effective_date)->toDateString() ?? (string) $batch->effective_date
            ),
            'canManageStaff' => $this->canManageStaff($batch),
            'canManageNonStaff' => $this->canManageNonStaff($batch),
            'canUnlock'     => $this->canUnlock($batch),
            'staffOptions'  => $this->staffOptions($batch),
        ]);
    }

    private function canManageStaff(WageRevisionBatch $batch): bool
    {
        try {
            $this->authorize('manageStaff', $batch);

            return true;
        } catch (Throwable) {
            return false;
        }
    }

    private function canManageNonStaff(WageRevisionBatch $batch): bool
    {
        try {
            $this->authorize('manageNonStaff', $batch);

            return true;
        } catch (Throwable) {
            return false;
        }
    }

    private function canUnlock(WageRevisionBatch $batch): bool
    {
        try {
            $this->authorize('unlock', $batch);

            return true;
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * Daftar NPK staff/expat aktif pada tanggal efektif, untuk pilihan Select2 pada form gaji
     * per orang (cari lewat NPK atau nama). Segmennya sama dengan yang divalidasi
     * `saveStaffItem()`, jadi pilihan yang tampil selalu bisa disimpan.
     * Daftar tidak dikirim kalau pengguna tidak berwenang mengisi gaji staff/expat.
     */
    private function staffOptions(WageRevisionBatch $batch): array
    {
        if (!$this->canManageStaff($batch)) {
            return [];
        }

        $effectiveDate = optional($batch->effective_date)->toDateString() ?? (string) $batch->effective_date;

        return $this->builder
            ->candidateQuery($effectiveDate, WageRevisionItem::SEGMENT_STAFF_EXPAT)
            ->get()
            ->map(function ($row) {
                $nama = trim((string) $row->NAMA_KARYAWAN);

                return [
                    'npk'  => (string) $row->NPK,
                    'nama' => $nama !== '' ? $nama : '(nama kosong)',
                    'dept' => trim((string) ($row->DEPARTEMENT ?? '')),
                ];
            })
            ->unique('npk')
            ->sortBy('nama', SORT_NATURAL | SORT_FLAG_CASE)
            ->values()
            ->all();
    }

    private function stats(WageRevisionBatch $batch): array
    {
        $items = $batch->items()->get();

        return [
            'total'       => $items->count(),
            'non_staff'   => $items->where('segment', WageRevisionItem::SEGMENT_NON_STAFF)->count(),
            'staff'       => $items->where('segment', WageRevisionItem::SEGMENT_STAFF_EXPAT)->count(),
            'valid'       => $items->where('status', WageRevisionItem::STATUS_VALID)->count(),
            'invalid'     => $items->where('status', WageRevisionItem::STATUS_INVALID)->count(),
            'skipped'     => $items->where('status', WageRevisionItem::STATUS_SKIPPED)->count(),
            'applied'     => $items->where('status', WageRevisionItem::STATUS_APPLIED)->count(),
            'failed'      => $items->where('status', WageRevisionItem::STATUS_FAILED)->count(),
            'warn_umr'    => $items->where('warn_below_umr', true)->count(),
            'warn_lembur' => $items->where('warn_overtime_threshold', true)->count(),
            'delta'       => (float) $items->where('status', WageRevisionItem::STATUS_VALID)->sum('delta_monthly'),
        ];
    }

    /** Hitung ulang item non-staff dari persentase batch. Item yang diketik/diimpor tidak diubah. */
    public function generate(WageRevisionBatch $batch)
    {
        $this->authorize('manageNonStaff', $batch);

        if (!$batch->isEditable()) {
            Alert::error('Gagal', 'Batch sudah disetujui, item tidak bisa dihitung ulang.');

            return redirect()->route('wage-revisions.show', $batch->id);
        }

        $this->builder->clearGeneratedItems($batch);

        $role  = $this->role();
        $stats = $this->builder->buildNonStaffItems($batch, $this->isAdmin() ? null : $role);

        $this->markSimulated($batch);

        Alert::success('Hitung ulang selesai', sprintf(
            'Baru: %d, diperbarui: %d, tidak diproses: %d, ditolak: %d, item manual dipertahankan: %d.',
            $stats['created'],
            $stats['updated'],
            $stats['skipped'],
            $stats['invalid'],
            $stats['kept']
        ));

        return redirect()->route('wage-revisions.show', $batch->id);
    }

    /** Simpan satu nominal gaji baru untuk staff/expat. */
    public function staff(Request $request, WageRevisionBatch $batch)
    {
        $this->authorize('manageStaff', $batch);

        $data = $request->validate([
            'npk'   => ['required', 'string', 'max:50'],
            'mode'  => ['required', 'in:target,delta'],
            'value' => ['required', 'numeric', 'min:0'],
            'note'  => ['nullable', 'string', 'max:255'],
        ], [], [
            'npk'   => 'NPK',
            'value' => 'nominal gaji',
        ]);

        $item = $this->builder->saveStaffItem(
            $batch,
            strtoupper(trim($data['npk'])),
            (float) $data['value'],
            $data['mode'],
            'manual',
            $data['note'] ?? null
        );

        $this->markSimulated($batch);

        if ($request->expectsJson()) {
            return response()->json([
                'ok'      => $item->status === WageRevisionItem::STATUS_VALID,
                'status'  => $item->status,
                'message' => $item->message,
            ]);
        }

        $item->status === WageRevisionItem::STATUS_VALID
            ? Alert::success('Tersimpan', 'Gaji baru ' . $item->npk . ' berhasil dihitung.')
            : Alert::error('Ditolak', (string) $item->message);

        return redirect()->route('wage-revisions.show', $batch->id);
    }

    public function import(Request $request, WageRevisionBatch $batch)
    {
        $this->authorize('manageStaff', $batch);

        $request->validate([
            'file' => ['required', 'file', 'mimes:xlsx,xls,csv', 'max:5120'],
        ], [], ['file' => 'berkas Excel']);

        $import = new WageRevisionItemImport($batch, $this->builder);

        try {
            Excel::import($import, $request->file('file'));
        } catch (Throwable $e) {
            Alert::error('Import gagal', 'Berkas tidak bisa dibaca: ' . $e->getMessage());

            return redirect()->route('wage-revisions.show', $batch->id);
        }

        $this->markSimulated($batch);

        Alert::success('Import selesai', sprintf(
            'Valid: %d, ditolak: %d, tidak diproses: %d.',
            $import->valid,
            $import->invalid,
            $import->skipped
        ));

        return redirect()->route('wage-revisions.show', $batch->id)
            ->with('import_report', $import->report);
    }

    public function template(WageRevisionBatch $batch)
    {
        $this->authorize('manageStaff', $batch);

        $effectiveDate = optional($batch->effective_date)->toDateString() ?? (string) $batch->effective_date;

        $candidates = $this->builder
            ->candidateQuery($effectiveDate, WageRevisionItem::SEGMENT_STAFF_EXPAT)
            ->get();

        $contracts = $this->builder->contractsFor($candidates->pluck('NPK')->all(), $effectiveDate);

        $rows = $candidates->map(function ($row) use ($contracts) {
            $contract = $contracts->get((string) $row->NPK);

            return [
                'npk'                => (string) $row->NPK,
                'nama'               => (string) ($row->NAMA_KARYAWAN ?? ''),
                'departemen'         => (string) ($row->DEPARTEMENT ?? $row->SECTION ?? ''),
                'is_staff'           => (string) ($row->IS_STAFF ?? ''),
                'is_expat'           => (string) ($row->IS_EXPAT ?? ''),
                'gaji_pokok_sekarang' => $contract ? (float) $contract->salary : '',
            ];
        })->values();

        return Excel::download(
            new WageRevisionTemplateExport($batch, $rows),
            'template_gaji_staff_' . $batch->year . '.xlsx'
        );
    }

    public function export(WageRevisionBatch $batch)
    {
        $this->authorize('view', $batch);

        return Excel::download(new WageRevisionResultExport($batch), 'penyesuaian_upah_' . $batch->year . '.xlsx');
    }

    public function approve(WageRevisionBatch $batch)
    {
        $this->authorize('approve', $batch);

        $stats = $this->stats($batch);

        if ($stats['valid'] === 0) {
            Alert::error('Gagal', 'Belum ada item valid untuk disetujui.');

            return redirect()->route('wage-revisions.show', $batch->id);
        }

        $batch->update([
            'status'      => WageRevisionBatch::STATUS_APPROVED,
            'approved_by' => Auth::id(),
        ]);

        $pesan = 'Batch disetujui. Item valid: ' . $stats['valid'] . '.';

        if ($stats['invalid'] > 0) {
            $pesan .= ' Item ditolak ' . $stats['invalid'] . ' tidak akan diterapkan.';
        }

        Alert::success('Disetujui', $pesan);

        return redirect()->route('wage-revisions.show', $batch->id);
    }

    public function apply(WageRevisionBatch $batch)
    {
        $this->authorize('apply', $batch);

        $effectiveDate = optional($batch->effective_date)->toDateString() ?? (string) $batch->effective_date;

        if ($this->applier->payrollRunExistsFor($effectiveDate)) {
            Alert::error('Ditolak', 'Payroll periode ' . $effectiveDate . ' sudah dijalankan, penyesuaian tidak boleh diterapkan.');

            return redirect()->route('wage-revisions.show', $batch->id);
        }

        $result = $this->applier->apply($batch, Auth::id());

        if ($result['error']) {
            Alert::error('Gagal', $result['error']);
        } elseif ($result['failed'] > 0) {
            Alert::warning('Sebagian gagal', 'Berhasil: ' . $result['applied'] . ', gagal: ' . $result['failed'] . '. Perbaiki lalu ulangi.');
        } else {
            Alert::success('Diterapkan', 'Gaji baru diterapkan ke ' . $result['applied'] . ' kontrak karyawan.');
        }

        return redirect()->route('wage-revisions.show', $batch->id);
    }

    public function rollback(WageRevisionBatch $batch)
    {
        $this->authorize('rollback', $batch);

        $result = $this->applier->rollback($batch, Auth::id());

        if ($result['error']) {
            Alert::error('Gagal', $result['error']);
        } elseif ($result['failed'] > 0) {
            Alert::warning('Sebagian gagal', 'Dikembalikan: ' . $result['restored'] . ', gagal: ' . $result['failed'] . '.');
        } else {
            Alert::success('Dibatalkan', 'Gaji lama dikembalikan untuk ' . $result['restored'] . ' karyawan.');
        }

        return redirect()->route('wage-revisions.show', $batch->id);
    }

    /** Buka kunci batch yang sudah dibatalkan supaya bisa diproses lagi. Hanya Admin. */
    public function unlock(WageRevisionBatch $batch)
    {
        $this->authorize('unlock', $batch);

        $result = $this->applier->unlock($batch);

        if ($result['error']) {
            Alert::error('Gagal', $result['error']);
        } else {
            $pesan = 'Batch bisa diproses lagi (status simulasi). ' . $result['restored_items'] . ' item dikembalikan ke status valid';

            if ($result['cleared_ledger'] > 0) {
                $pesan .= ', catatan penerapan lama dibersihkan';
            }

            Alert::success('Kunci dibuka', $pesan . '. Hitung ulang bila perlu, lalu Setujui dan Terapkan lagi.');
        }

        return redirect()->route('wage-revisions.show', $batch->id);
    }

    public function destroyItem(WageRevisionBatch $batch, WageRevisionItem $item)
    {
        $this->authorize('manageStaff', $batch);

        if ((int) $item->batch_id !== (int) $batch->id) {
            abort(404);
        }

        $item->delete();

        Alert::success('Dihapus', 'Item ' . $item->npk . ' dihapus dari batch.');

        return redirect()->route('wage-revisions.show', $batch->id);
    }

    public function destroy(WageRevisionBatch $batch)
    {
        $this->authorize('delete', $batch);

        if (!$batch->isEditable()) {
            Alert::error('Gagal', 'Hanya batch draft/simulasi yang bisa dihapus.');

            return redirect()->route('wage-revisions.show', $batch->id);
        }

        $year = $batch->year;

        $batch->items()->delete();
        $batch->rules()->delete();
        $batch->delete();

        Alert::success('Dihapus', 'Batch ' . $year . ' dihapus.');

        return redirect()->route('wage-revisions.index');
    }

    private function markSimulated(WageRevisionBatch $batch): void
    {
        if ($batch->status === WageRevisionBatch::STATUS_DRAFT && $batch->items()->exists()) {
            $batch->update(['status' => WageRevisionBatch::STATUS_SIMULATED]);
        }
    }
}
