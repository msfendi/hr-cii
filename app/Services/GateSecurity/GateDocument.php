<?php

namespace App\Services\GateSecurity;

/**
 * Kontrak dokumen yang diperiksa di pos security (scan QR saat keluar/kembali).
 *
 * Dipakai supaya halaman Security tidak perlu tahu dokumen apa yang discan:
 * semua perbedaan diserahkan ke implementasi. Menambah jenis dokumen baru =
 * satu kelas adapter + satu baris di GateRegistry.
 */
interface GateDocument
{
    /**
     * Kode dokumen yang dipakai di QR dan di registry.
     * Contoh: 'izin-dinas'.
     */
    public static function docType(): string;

    /** Nama dokumen untuk ditampilkan ke petugas. Contoh: 'Izin Dinas'. */
    public static function label(): string;

    /**
     * Cari dokumen dari isi QR.
     *
     * @param  string|null  $token  token pada QR; null kalau QR tidak membawa token
     * @return static|null          null kalau tidak ditemukan / token tidak cocok
     */
    public static function find(int $id, ?string $token): ?self;

    public function id(): int;

    public function token(): string;

    /**
     * Pemohon dokumen.
     *
     * @return array{npk:string, name:string}
     */
    public function applicant(): array;

    /**
     * Apakah dokumen sudah boleh diproses gate (mis. semua approval selesai).
     */
    public function isReady(): bool;

    /**
     * Tahap gate yang sedang menunggu DAN boleh diproses: 'out', 'in', atau
     * null kalau dokumen belum siap (approval belum lengkap) atau sudah selesai.
     *
     * Sengaja null saat belum siap: pemanggil tidak boleh menyimpulkan ada
     * tahap menunggu dari data yang belum boleh diubah.
     */
    public function stage(): ?string;

    /**
     * Baris ringkasan yang ditampilkan ke petugas.
     *
     * Sengaja array label–nilai, bukan markup, supaya view gate tetap generik
     * dan tidak perlu tahu kolom dokumen apa pun.
     *
     * @return array<int, array{label:string, value:string}>
     */
    public function summary(): array;

    /**
     * Catatan gate yang sudah ada.
     *
     * @return array{out:?array<string,mixed>, in:?array<string,mixed>}
     */
    public function gates(): array;

    /**
     * Catat gate check.
     *
     * @return array{ok:bool, message:string, stage?:string}
     */
    public function recordGate(string $stage, string $officerNpk, string $officerName, ?string $note = null): array;
}