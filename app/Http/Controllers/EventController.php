<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Event;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

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

    /* ------------------------------------------------------------ */
    /*  Helpers                                                        */
    /* ------------------------------------------------------------ */

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
