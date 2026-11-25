<?php

namespace App\Console\Commands;

use App\Models\IjinMeninggalkanRequest;
use App\Models\WorkAssignmentRequest;
use App\Services\IzinMeninggalkan\IzinMeninggalkanLegacySync;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Throwable;

/**
 * Auto-isi `jam_kembali` = 17:00 untuk pengajuan yang sudah disetujui penuh
 * tetapi karyawannya tidak tercatat kembali oleh Security sampai jam tutup.
 *
 * Berlaku untuk (sementara):
 *   - Izin Dinas       → `work_assignment_requests`
 *   - Izin Meninggalkan → `ijin_meninggalkan_requests`
 *
 * Alasan: kalau `jam_kembali` dibiarkan kosong, rekap absensi & payroll
 * menganggap karyawan belum kembali sehingga potongan izin tidak terbentuk.
 * Worker ini menutup baris tersebut supaya batas hari tetap konsisten.
 *
 * Aturan:
 *   - Hanya menyentuh baris `status = approved` yang belum `void`.
 *   - Tanggal acuan: Izin Meninggalkan lewat kolom `tanggal`; Izin Dinas lewat
 *     rentang `start_date..end_date` (tanggal acuan harus di dalamnya).
 *   - Hanya baris yang `jam_kembali` masih kosong — yang sudah terisi
 *     by security TIDAK ditimpa.
 *   - Tidak menulis `security_check` — pengisian ini bukan aksi petugas
 *     security, jadi jejak gate tetap apa adanya.
 *   - Khusus Izin Meninggalkan, setelah `jam_kembali` terisi, salinan ke
 *     tabel absensi lama (`ijin_meninggalkan_pekerjaans`) ikut dibuat —
 *     sama seperti yang terjadi saat Security mencatat karyawan kembali.
 *
 * Ambal: kalau karyawan ternyata scan kembali SETELAH 17:00, gate akan
 * memperbarui `jam_kembali` ke waktu scan aktual; baris lama di tabel legacy
 * tidak ikut berubah karena sinkronisasi bersifat idempoten.
 *
 * Contoh:
 *   php artisan izin:auto-fill-jam-kembali --dry-run
 *   php artisan izin:auto-fill-jam-kembali --date=2026-11-25 --time=17:00
 *   php artisan izin:auto-fill-jam-kembali --doc=izin-meninggalkan
 */
class AutoFillJamKembali extends Command
{
    protected $signature = 'izin:auto-fill-jam-kembali
                            {--date= : Tanggal acuan (Y-m-d). Default: hari ini (WIB)}
                            {--time=17:00 : Jam pengisian kalau jam_kembali masih kosong}
                            {--doc=all : Dokumen yang diproses: all | izin-dinas | izin-meninggalkan}
                            {--dry-run : Tampilkan saja, tidak menyimpan}';

    protected $description = 'Isi otomatis jam_kembali (default 17:00) untuk Izin Dinas & Izin Meninggalkan yang sudah approved tapi belum scan kembali.';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $docOpt = strtolower((string) $this->option('doc'));

        if (!in_array($docOpt, ['all', 'izin-dinas', 'izin-meninggalkan'], true)) {
            $this->error("Opsi --doc tidak dikenal: {$docOpt}. Pakai all | izin-dinas | izin-meninggalkan.");

            return self::INVALID;
        }

        $date = $this->option('date');
        try {
            $date = $date !== null && $date !== ''
                ? Carbon::parse((string) $date, 'Asia/Jakarta')->toDateString()
                : now('Asia/Jakarta')->toDateString();
        } catch (Throwable $e) {
            $this->error('Tanggal acuan tidak valid: ' . $e->getMessage());

            return self::INVALID;
        }

        $time = $this->normalizeTime((string) $this->option('time'));

        $this->info(
            "Auto-isi jam_kembali = {$time} untuk tanggal {$date}"
            . ($dryRun ? ' (dry-run)' : '')
        );

        $failures = 0;

        if (in_array($docOpt, ['all', 'izin-dinas'], true)) {
            $failures += $this->processIzinDinas($date, $time, $dryRun);
        }

        if (in_array($docOpt, ['all', 'izin-meninggalkan'], true)) {
            $failures += $this->processIzinMeninggalkan($date, $time, $dryRun);
        }

        if ($failures > 0) {
            $this->warn("Selesai dengan {$failures} baris gagal diproses.");

            return self::FAILURE;
        }

        $this->info('Selesai.');

        return self::SUCCESS;
    }

    /** Izin Dinas (Surat Ijin Dinas Luar) — tanpa salinan tabel legacy. */
    private function processIzinDinas(string $date, string $time, bool $dryRun): int
    {
        $this->line('');
        $this->line('— Izin Dinas (work_assignment_requests) —');

        try {
            $rows = $this->pendingQuery(
                WorkAssignmentRequest::query(),
                // Izin Dinas memakai rentang tanggal dinas (start_date..end_date),
                // bukan satu kolom tanggal seperti Izin Meninggalkan.
                fn ($q) => $q->whereDate('start_date', '<=', $date)
                    ->where(function ($x) use ($date) {
                        $x->whereNull('end_date')->orWhereDate('end_date', '>=', $date);
                    })
            )->get(['id', 'npk']);
        } catch (Throwable $e) {
            $this->error('  Gagal membaca data: ' . $e->getMessage());

            return 1;
        }

        if ($rows->isEmpty()) {
            $this->line('  tidak ada baris yang perlu diisi.');

            return 0;
        }

        $filled = 0;
        $failed = 0;

        foreach ($rows as $row) {
            if ($this->fill(WorkAssignmentRequest::class, (int) $row->id, $time, $dryRun)) {
                $filled++;
                $this->line('  ' . ($dryRun ? '[dry] ' : '') . "#{$row->id} ({$row->npk}) → {$time}");
            } else {
                $failed++;
                $this->warn("  #{$row->id} gagal disimpan.");
            }
        }

        $this->line("  total: {$filled} diisi" . ($failed > 0 ? ", {$failed} gagal" : '') . '.');

        return $failed;
    }

    /** Izin Meninggalkan — ikut membuat salinan ke tabel absensi lama. */
    private function processIzinMeninggalkan(string $date, string $time, bool $dryRun): int
    {
        $this->line('');
        $this->line('— Izin Meninggalkan (ijin_meninggalkan_requests) —');

        try {
            $rows = $this->pendingQuery(
                IjinMeninggalkanRequest::query(),
                fn ($q) => $q->whereDate('tanggal', $date)
            )->get(['id', 'npk']);
        } catch (Throwable $e) {
            $this->error('  Gagal membaca data: ' . $e->getMessage());

            return 1;
        }

        if ($rows->isEmpty()) {
            $this->line('  tidak ada baris yang perlu diisi.');

            return 0;
        }

        $filled = 0;
        $failed = 0;

        foreach ($rows as $row) {
            $id = (int) $row->id;

            if ($dryRun) {
                $this->line("  [dry] #{$id} ({$row->npk}) → {$time} (+ sinkron absensi lama)");
                $filled++;
                continue;
            }

            if (!$this->fill(IjinMeninggalkanRequest::class, $id, $time, false)) {
                $failed++;
                $this->warn("  #{$id} gagal disimpan.");
                continue;
            }

            // Samakan dengan gate 'in': setelah jam_kembali terisi, salinan ke
            // tabel absensi lama dibuat supaya payroll melihat potongannya.
            try {
                $fresh = IjinMeninggalkanRequest::find($id);

                if ($fresh) {
                    $legacyId = IzinMeninggalkanLegacySync::sync($fresh);
                    $this->line("  #{$id} ({$row->npk}) → {$time}" . ($legacyId ? " (legacy #{$legacyId})" : ''));
                }
            } catch (Throwable $e) {
                $failed++;
                $this->warn("  #{$id} terisi tapi sinkron absensi lama gagal: " . $e->getMessage());
                continue;
            }

            $filled++;
        }

        $this->line("  total: {$filled} diisi" . ($failed > 0 ? ", {$failed} gagal" : '') . '.');

        return $failed;
    }

    /**
     * Baris yang siap diisi: approved, tidak void, cocok tanggal acuan,
     * jam_kembali kosong.
     *
     * $dateFilter dipakai karena tiap dokumen beda: Izin Dinas memakai rentang
     * `start_date..end_date`, Izin Meninggalkan satu kolom `tanggal`.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<*>  $query
     * @param  callable(\Illuminate\Database\Eloquent\Builder<*>): mixed  $dateFilter
     * @return \Illuminate\Database\Eloquent\Builder<*>
     */
    private function pendingQuery($query, callable $dateFilter)
    {
        return $query
            ->where('status', 'approved')
            ->where(function ($q) {
                $q->whereNull('void')->orWhereNotIn('void', ['true', '1']);
            })
            ->where($dateFilter)
            ->where(function ($q) {
                $q->whereNull('jam_kembali')->orWhere('jam_kembali', '');
            })
            ->orderBy('id');
    }

    /**
     * Isi `jam_kembali` satu baris. False kalau baris hilang / gagal simpan.
     *
     * @param  class-string<\Illuminate\Database\Eloquent\Model>  $model
     */
    private function fill(string $model, int $id, string $time, bool $dryRun): bool
    {
        $record = $model::find($id);

        if (!$record) {
            return false;
        }

        // Lapis keamanan: pastikan masih kosong (hindari balapan dengan gate scan).
        if (!empty($record->jam_kembali)) {
            return true;
        }

        if ($dryRun) {
            return true;
        }

        $record->jam_kembali = $time;

        return (bool) $record->save();
    }

    /** Normalisasi '17:00' → '17:00:00'. */
    private function normalizeTime(string $time): string
    {
        $time = trim($time);

        if (preg_match('/^\d{1,2}:\d{2}$/', $time)) {
            return strlen($time) === 4 ? "0{$time}:00" : "{$time}:00";
        }

        if (preg_match('/^\d{1,2}:\d{2}:\d{2}$/', $time)) {
            return strlen($time) === 7 ? "0{$time}" : $time;
        }

        return $time;
    }
}
