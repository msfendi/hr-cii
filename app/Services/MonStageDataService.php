<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Service pendukung untuk fitur import Stage Remark (mon_stage_remarks) &
 * Prod QC (mon_prod_qc) di dashboard Rekonsiliasi OCF.
 *
 * Daftar dropdown `ocf_no` (mon_stage_remarks) dan `code_prod` (mon_prod_qc)
 * SUMBERNYA SAMA: hasil ekstraksi mon_rekonsiliasis.spesifikasi, format teks
 * bebas "... OCF <kode> ..." -- formatnya TIDAK konsisten, titik dua
 * setelah "OCF" kadang ada kadang tidak, contoh:
 *   "CPO 25027 OCF 256C0005"        -> "256C0005"
 *   "CPO 25027 OCF: 256C0005"       -> "256C0005"
 *   "26063 OCF 266C0038 A - A1"     -> "266C0038"
 * Ambil token pertama setelah kata "OCF" (titik dua opsional), distinct,
 * urut naik. Dipakai sebagai dropdown (data validation) di file template
 * Excel export, dan boleh dipakai validasi server-side saat import kalau
 * diperlukan.
 */
class MonStageDataService
{
    /** TTL cache daftar OCF (jarang berubah dalam waktu singkat, aman di-cache). */
    private const OCF_LIST_TTL = 300; // 5 menit

    /** Daftar department tetap untuk kolom department_id di kedua tabel. */
    public const DEPARTMENTS = ['Cutting', 'Sewing', 'Packing', 'QC', 'Balance Garment Stock', 'Warehouse'];

    /**
     * Daftar kode OCF hasil ekstraksi dari mon_rekonsiliasis.spesifikasi,
     * distinct & urut naik. Dipakai untuk dropdown ocf_no (mon_stage_remarks)
     * MAUPUN dropdown code_prod (mon_prod_qc) -- keduanya sumbernya sama.
     */
    public function distinctOcfList(): array
    {
        return Cache::remember('mon_stage_data.ocf_list', self::OCF_LIST_TTL, function () {
            return DB::table('mon_rekonsiliasis')
                ->whereNotNull('spesifikasi')
                ->pluck('spesifikasi')
                ->map(fn($spesifikasi) => $this->extractOcfCode((string) $spesifikasi))
                ->filter()
                ->unique()
                ->sort()
                ->values()
                ->all();
        });
    }

    /**
     * Ekstrak kode OCF dari satu baris teks bebas spesifikasi. Return null
     * kalau pola "OCF <kode>" tidak ditemukan.
     *
     * Format sumbernya TIDAK konsisten -- kadang "OCF 256C0005", kadang
     * "OCF: 256C0005" / "OCF : 256C0005" (ada titik dua, dengan atau tanpa
     * spasi sebelum titik duanya) -- jadi titik dua dibuat opsional di
     * regex-nya (`:?`), bukan cuma spasi.
     */
    public function extractOcfCode(string $spesifikasi): ?string
    {
        if (preg_match('/OCF\s*:?\s*([A-Za-z0-9]+)/i', $spesifikasi, $m)) {
            return strtoupper(trim($m[1]));
        }

        return null;
    }

    public function isValidDepartment(?string $value): bool
    {
        return in_array($value, self::DEPARTMENTS, true);
    }
}
