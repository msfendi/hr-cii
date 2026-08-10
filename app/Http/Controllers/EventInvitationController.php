<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\EventInvitation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class EventInvitationController extends Controller
{
    protected const SESSION_KEY = 'event_invite';

    /**
     * Halaman scan QR NPK sebelum bisa mengisi form kehadiran.
     * $event boleh kosong -> otomatis pakai event yang sedang is_active.
     */
    public function scanPage(Request $request, $event = null): View
    {
        $eventModel = $this->resolvePublicEvent($event);

        if (!$eventModel) {
            return view('event_invitation.expired', [
                'event' => $this->findEventEvenIfInactive($event),
            ]);
        }

        return view("event_invitation.{$eventModel->view_folder}.scan", [
            'event' => $eventModel,
        ]);
    }

    /**
     * Endpoint dipanggil setiap kali ZXing berhasil decode QR.
     * Format QR: "C-00827_DIMAS GALANG RAMADHAN" atau "C-00827".
     */
    public function storeScan(Request $request, $event = null): JsonResponse
    {
        $eventModel = $this->resolvePublicEvent($event);

        if (!$eventModel) {
            return response()->json([
                'status'  => 'expired',
                'message' => 'Event ini sudah tidak aktif / berakhir.',
            ], 410);
        }

        $event = $eventModel;
        $rawQr = trim((string) $request->input('qr_code', $request->input('npk', '')));

        if ($rawQr === '') {
            return response()->json([
                'status'  => 'invalid',
                'message' => 'QR code tidak terbaca / kosong.',
            ], 422);
        }

        if (!preg_match('/^([A-Za-z]-\d{5})(?:_.*)?$/', strtoupper($rawQr), $matches)) {
            return response()->json([
                'status'  => 'invalid',
                'message' => 'Format QR Code tidak valid. Contoh format yang benar: C-00827_NAMA KARYAWAN',
            ], 422);
        }

        $npk = $matches[1];

        $employee = $this->findEmployeeByNpk($npk);
        if (!$employee) {
            return response()->json([
                'status'  => 'not_found',
                'message' => "NPK {$npk} tidak ditemukan di data karyawan.",
            ], 404);
        }

        $nama       = $employee->NAMA_KARYAWAN ?? '-';
        $departemen = $employee->BAG ?? '-';

        $request->session()->put(self::SESSION_KEY, [
            'event_id'   => $event->id,
            'npk'        => $npk,
            'nama'       => $nama,
            'departemen' => $departemen,
        ]);

        $existing = EventInvitation::where('event_id', $event->id)->where('npk', $npk)->first();

        return response()->json([
            'status'   => $existing?->is_confirmed ? 'already_registered' : 'success',
            'message'  => $existing?->is_confirmed
                ? "NPK {$npk} sudah pernah mengisi konfirmasi kehadiran, mengarahkan ke halaman undangan..."
                : "Selamat datang, {$nama}!",
            'redirect' => route('event-invitation.form', ['event' => $event->id]),
        ]);
    }

    /**
     * Halaman undangan utama (cover, countdown, doorprize, form RSVP).
     * Hanya bisa diakses kalau session hasil scan untuk event ini ada.
     */
    public function formPage(Request $request, $event = null): View|RedirectResponse
    {
        $eventModel = $this->resolvePublicEvent($event);

        if (!$eventModel) {
            return view('event_invitation.expired', [
                'event' => $this->findEventEvenIfInactive($event),
            ]);
        }

        $event   = $eventModel;
        $session = $request->session()->get(self::SESSION_KEY);

        if (!$session || (int) ($session['event_id'] ?? 0) !== $event->id) {
            return redirect()
                ->route('event-invitation.scan', ['event' => $event->id])
                ->with('error', 'Silakan scan QR Code undangan Anda terlebih dahulu.');
        }

        $invitation = EventInvitation::where('event_id', $event->id)
            ->where('npk', $session['npk'])
            ->first();

        return view("event_invitation.{$event->view_folder}.form", [
            'event'      => $event,
            'npk'        => $session['npk'],
            'nama'       => $session['nama'],
            'departemen' => $session['departemen'],
            'invitation' => $invitation,
        ]);
    }

    /**
     * Simpan jawaban "Hadir" / "Tidak Hadir" + ucapan (opsional), lalu
     * update counter jumlah_hadir / jumlah_tidak_hadir di tabel events.
     * NPK diambil dari session, bukan dari input client.
     */
    public function storeResponse(Request $request, $event = null): JsonResponse
    {
        $eventModel = $this->resolvePublicEvent($event);

        if (!$eventModel) {
            return response()->json([
                'status'  => 'expired',
                'message' => 'Event ini sudah tidak aktif / berakhir.',
            ], 410);
        }

        $event   = $eventModel;
        $session = $request->session()->get(self::SESSION_KEY);

        if (!$session || (int) ($session['event_id'] ?? 0) !== $event->id) {
            return response()->json([
                'status'  => 'invalid',
                'message' => 'Sesi scan tidak ditemukan atau sudah kadaluarsa. Silakan scan ulang QR Code Anda.',
            ], 419);
        }

        $validated = $request->validate([
            'status' => 'required|in:hadir,tidak_hadir',
            'ucapan' => 'nullable|string|max:1000',
        ]);

        $invitation = DB::transaction(function () use ($event, $session, $validated, $request) {
            $existing       = EventInvitation::where('event_id', $event->id)->where('npk', $session['npk'])->first();
            $previousStatus = $existing?->status;

            $invitation = EventInvitation::updateOrCreate(
                ['event_id' => $event->id, 'npk' => $session['npk']],
                [
                    'nama'         => $session['nama'],
                    'departemen'   => $session['departemen'],
                    'status'       => $validated['status'],
                    'ucapan'       => $validated['ucapan'] ?? null,
                    'ip_address'   => $request->ip(),
                    'responded_at' => now(),
                ]
            );

            // Jaga rekap jumlah_hadir / jumlah_tidak_hadir di tabel events
            // tetap akurat walau seseorang mengubah jawabannya.
            if ($previousStatus !== $validated['status']) {
                if ($previousStatus === 'hadir') {
                    $event->decrement('jumlah_hadir');
                } elseif ($previousStatus === 'tidak_hadir') {
                    $event->decrement('jumlah_tidak_hadir');
                }

                $validated['status'] === 'hadir'
                    ? $event->increment('jumlah_hadir')
                    : $event->increment('jumlah_tidak_hadir');
            }

            return $invitation;
        });

        return response()->json([
            'status'  => 'success',
            'message' => $validated['status'] === 'hadir'
                ? 'Terima kasih! Kehadiran Anda sudah kami catat. Sampai jumpa di acara! 🎉🇮🇩'
                : 'Terima kasih atas konfirmasinya. Semoga bisa bergabung di lain kesempatan!',
            'data' => [
                'npk'    => $invitation->npk,
                'nama'   => $invitation->nama,
                'status' => $invitation->status,
            ],
        ]);
    }

    /* ------------------------------------------------------------ */
    /*  Helpers                                                        */
    /* ------------------------------------------------------------ */

    /**
     * Dipakai khusus untuk halaman/endpoint publik (scan, form, respond).
     * Berbeda dengan resolveEvent(), method ini TIDAK 404 kalau event tidak
     * ditemukan atau sudah nonaktif -> cukup kembalikan null, supaya caller
     * bisa menampilkan halaman "Event Sudah Berakhir" alih-alih error 404.
     *
     * - $event diisi (id dari route lama/di-bookmark user) tapi:
     *      - tidak ketemu di DB -> null
     *      - ketemu tapi is_active = 0 -> null
     * - $event kosong -> pakai event yang sedang is_active (kalau tidak ada -> null)
     */
    private function resolvePublicEvent($event): ?Event
    {
        if (!empty($event)) {
            $found = Event::find($event);

            return ($found && $found->is_active) ? $found : null;
        }

        return Event::active()->first();
    }

    /**
     * Ambil data event walau statusnya sudah nonaktif, khusus supaya halaman
     * "Event Sudah Berakhir" tetap bisa menampilkan nama event-nya (kalau ada).
     * Kembalikan null kalau id tidak diisi atau memang tidak ketemu sama sekali.
     */
    private function findEventEvenIfInactive($event): ?Event
    {
        if (empty($event)) {
            return null;
        }

        return Event::find($event);
    }

    /**
     * Cari data karyawan berdasarkan NPK dari BIODATA UNION BIODATA_KELUAR
     * lewat koneksi database "cii", join ke DEPT untuk nama department.
     */
    private function findEmployeeByNpk(string $npk)
    {
        $sql = "
            SELECT EMP.NPK, EMP.NAMA_KARYAWAN, EMP.BAG, EMP.ID_DEPT, EMP.JENIS_KEL,
                   EMP.SECTION, EMP.STATUS, EMP.IS_STAFF, EMP.IS_EXPAT,
                   D.DEPARTEMENT
            FROM (
                SELECT NPK, NAMA_KARYAWAN, BAG, ID_DEPT, JENIS_KEL, SECTION, STATUS, IS_STAFF, IS_EXPAT
                FROM BIODATA WHERE NPK = ?
                UNION
                SELECT NPK, NAMA_KARYAWAN, BAG, ID_DEPT, JENIS_KEL, SECTION, STATUS, IS_STAFF, IS_EXPAT
                FROM BIODATA_KELUAR WHERE NPK = ?
            ) AS EMP
            LEFT JOIN DEPT AS D ON D.ID_DEPT = EMP.ID_DEPT
        ";

        $result = DB::connection('cii')->select($sql, [$npk, $npk]);

        return $result[0] ?? null;
    }
}
