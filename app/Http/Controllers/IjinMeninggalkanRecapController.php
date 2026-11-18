<?php

namespace App\Http\Controllers;

use App\Models\IjinMeninggalkanRequest;
use App\Services\WorkAssignment\EmployeeLookupService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Rekap Izin Meninggalkan Tugas / Pekerjaan (admin).
 *
 * Menampilkan SEMUA pengajuan dari semua pemohon. Berbeda dari halaman
 * approval yang hanya menampilkan pengajuan milik rantai approver tersebut.
 *
 * Pembatalan memakai void, bukan hapus fisik: baris ini adalah dasar data yang
 * nanti disalin ke tabel absensi payroll, jadi jejaknya harus tetap ada.
 */
class IjinMeninggalkanRecapController extends Controller
{
    public function index()
    {
        return view('izin_meninggalkan.recap');
    }

    /**
     * Data untuk DataTables.
     */
    public function getData(Request $request)
    {
        $status = (string) $request->input('status', 'all');

        $requests = IjinMeninggalkanRequest::orderByDesc('id')->get();

        $rows = [];

        foreach ($requests as $req) {
            $progress = $req->progress();
            $isVoid   = in_array(strtolower((string) $req->void), ['true', '1'], true);

            if ($status !== 'all' && $progress['status'] !== $status) {
                continue;
            }

            $rows[] = [
                'id'              => (int) $req->id,
                'npk'             => (string) $req->npk,
                'nama'            => null,
                'tanggal'         => $req->tanggal?->format('d-m-Y'),
                'jam_keluar'      => $req->jam_keluar ? substr((string) $req->jam_keluar, 0, 5) : '-',
                'rencana_kembali' => $req->rencana_kembali ? substr((string) $req->rencana_kembali, 0, 5) : '-',
                'jam_kembali'     => $req->jam_kembali ? substr((string) $req->jam_kembali, 0, 5) : null,
                'alasan'          => (string) ($req->alasan ?? ''),
                'status'          => $isVoid ? 'Dibatalkan' : $progress['label'],
                'status_tone'     => $isVoid ? 'secondary' : $progress['tone'],
                'done'            => $progress['done'],
                'total'           => $progress['total'],
                'legacy_ijin_id'  => $req->legacy_ijin_id,
                'created_at'      => $req->created_at?->format('d-m-Y H:i'),
            ];
        }

        $names = EmployeeLookupService::namesFor(array_values(array_unique(array_column($rows, 'npk'))));

        foreach ($rows as &$row) {
            $row['nama'] = $names[$row['npk']] ?? $row['npk'];
        }
        unset($row);

        return response()->json([
            'draw'            => (int) $request->input('draw', 1),
            'data'            => $rows,
            'recordsTotal'    => count($rows),
            'recordsFiltered' => count($rows),
        ]);
    }

    /**
     * Detail satu pengajuan (modal).
     */
    public function detail($id)
    {
        $req = IjinMeninggalkanRequest::find($id);

        if (!$req) {
            return response()->json(['success' => false, 'message' => 'Pengajuan tidak ditemukan.'], 404);
        }

        $names    = EmployeeLookupService::namesFor([(string) $req->npk]);
        $progress = $req->progress();
        $isVoid   = in_array(strtolower((string) $req->void), ['true', '1'], true);

        return response()->json([
            'success' => true,
            'data'    => [
                'id'              => (int) $req->id,
                'npk'             => (string) $req->npk,
                'nama'            => $names[(string) $req->npk] ?? (string) $req->npk,
                'tanggal'         => $req->tanggal?->format('d-m-Y'),
                'jam_keluar'      => $req->jam_keluar ? substr((string) $req->jam_keluar, 0, 5) : '-',
                'rencana_kembali' => $req->rencana_kembali ? substr((string) $req->rencana_kembali, 0, 5) : null,
                'jam_kembali'     => $req->jam_kembali ? substr((string) $req->jam_kembali, 0, 5) : null,
                'alasan'          => (string) ($req->alasan ?? ''),
                'status'          => $isVoid ? 'Dibatalkan' : $progress['label'],
                'status_tone'     => $isVoid ? 'secondary' : $progress['tone'],
                'created_at'      => $req->created_at?->format('d-m-Y H:i'),
                'legacy_ijin_id'  => $req->legacy_ijin_id,
                'steps'           => $req->timeline(),
            ],
        ]);
    }

    /**
     * Batalkan pengajuan (void). Tidak menghapus baris.
     */
    public function destroy(Request $request, $id)
    {
        $req = IjinMeninggalkanRequest::find($id);

        if (!$req) {
            return response()->json([
                'success' => false,
                'message' => 'Pengajuan tidak ditemukan.',
            ], 404);
        }

        if (in_array(strtolower((string) $req->void), ['true', '1'], true)) {
            return response()->json([
                'success' => false,
                'message' => 'Pengajuan ini sudah dibatalkan.',
            ], 422);
        }

        // Sudah disalin ke tabel absensi → membatalkan di sini tidak otomatis
        // menghapus baris absensinya. Dicegah supaya data payroll tidak
        // berbeda diam-diam antara dua tabel.
        if ($req->legacy_ijin_id !== null) {
            return response()->json([
                'success' => false,
                'message' => 'Pengajuan sudah tercatat di data absensi (ijin #'
                    . $req->legacy_ijin_id . '). Batalkan lewat menu Ijin Meninggalkan Pekerjaan.',
            ], 422);
        }

        $request->validate([
            'reason' => 'nullable|string|max:500',
        ]);

        $reason = trim((string) $request->input('reason'));

        try {
            DB::connection('cii')->transaction(function () use ($req, $reason) {
                $answers = $req->answered();

                foreach ($answers as $i => $answer) {
                    $answers[$i]['note'] = trim(
                        (string) ($answer['note'] ?? '')
                        . ' [dibatalkan oleh admin: ' . ($reason !== '' ? $reason : 'tanpa alasan') . ']'
                    );
                }

                $req->answers = $answers;
                $req->void    = 'true';
                $req->status  = 'cancelled';
                $req->save();
            });
        } catch (\Throwable $e) {
            Log::error("[IzinMeninggalkan] batal #{$id} gagal: " . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Gagal membatalkan: ' . $e->getMessage(),
            ], 500);
        }

        Log::info("[IzinMeninggalkan] #{$id} dibatalkan admin. Alasan: " . ($reason ?: '-'));

        return response()->json([
            'success' => true,
            'message' => 'Pengajuan #' . $id . ' dibatalkan.',
        ]);
    }
}