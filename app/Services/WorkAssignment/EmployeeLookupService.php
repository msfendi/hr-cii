<?php

namespace App\Services\WorkAssignment;

use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Lookup data karyawan untuk form Work Assignment Request.
 *
 * Pemohon hanya memasukkan NPK; sisanya diambil dari master:
 *   - BIODATA  → NAMA_KARYAWAN, BAG, ID_DEPT, SECTION, STATUS
 *   - DEPT     → DEPARTEMENT (dipakai sebagai "Jabatan/Departemen")
 *   - sections → name
 *
 * Catatan: TIDAK ada master jabatan per-NPK di sistem. Kolom jabatan pada
 * dokumen diisi dengan DEPT.DEPARTEMENT, karena itu satu-satunya sumber yang
 * tersedia (approval_rules.name berisi jabatan sebagai *approver*, bukan
 * jabatan karyawan).
 *
 * Tabel `ijin_meninggalkan_pekerjaans` (fitur lain) tidak disentuh.
 */
class EmployeeLookupService
{
    /**
     * Data karyawan untuk autofill.
     *
     * @return array{ok:bool, message?:string, data?:array<string,mixed>}
     */
    public static function lookup(string $npk): array
    {
        $npk = trim($npk);

        if ($npk === '') {
            return ['ok' => false, 'message' => 'NPK wajib diisi.'];
        }

        try {
            $row = DB::connection('cii')
                ->table('BIODATA')
                ->leftJoin('DEPT', 'BIODATA.ID_DEPT', '=', 'DEPT.ID_DEPT')
                ->leftJoin('sections', 'BIODATA.SECTION', '=', 'sections.id')
                ->where('BIODATA.NPK', $npk)
                ->select(
                    'BIODATA.NPK',
                    'BIODATA.NAMA_KARYAWAN',
                    'BIODATA.BAG',
                    'BIODATA.SECTION',
                    'BIODATA.STATUS',
                    'DEPT.DEPARTEMENT',
                    'sections.name as SECTION_NAME'
                )
                ->first();
        } catch (Throwable $e) {
            return ['ok' => false, 'message' => 'Gagal membaca data karyawan: ' . $e->getMessage()];
        }

        if (!$row) {
            return ['ok' => false, 'message' => "NPK $npk tidak ditemukan pada data karyawan."];
        }

        // Karyawan keluar (STATUS != 'A') tidak boleh mengajukan.
        $status = strtoupper(trim((string) ($row->STATUS ?? '')));
        if ($status !== '' && $status !== 'A') {
            return ['ok' => false, 'message' => "NPK $npk tidak berstatus aktif (status: $status)."];
        }

        $bagian     = self::clean($row->BAG);
        $departemen = self::clean($row->DEPARTEMENT);
        $section    = self::clean($row->SECTION_NAME);

        return [
            'ok'   => true,
            'data' => [
                'npk'        => (string) $row->NPK,
                'nama'       => self::clean($row->NAMA_KARYAWAN) ?? (string) $row->NPK,
                'jabatan'    => $departemen,
                'bagian'     => $bagian,
                'departemen' => $departemen,
                'section'    => $section,
            ],
        ];
    }

    /**
     * Nama asli approver (bukan jabatan) untuk sekumpulan NPK.
     *
     * `approval_rules.name` berisi JABATAN approver (mis. "HRD MANAGER").
     * Nama orangnya ada di PKWT.NAMA — semua NPK approver ada di sana
     * (verified: 538/538).
     *
     * @param  array<int, string>  $npks
     * @return array<string, string>  NPK => nama
     */
    public static function namesFor(array $npks): array
    {
        $npks = array_values(array_filter(array_unique(array_map(
            fn ($n) => trim((string) $n),
            $npks
        ))));

        if (empty($npks)) {
            return [];
        }

        try {
            $rows = DB::connection('cii')
                ->table('PKWT')
                ->whereIn('NPK', $npks)
                ->select('NPK', 'NAMA')
                ->get();
        } catch (Throwable) {
            return [];
        }

        $names = [];

        foreach ($rows as $row) {
            $name = self::clean($row->NAMA);
            if ($name !== null) {
                $names[(string) $row->NPK] = $name;
            }
        }

        return $names;
    }

    /**
     * Rentang tanggal yang boleh diajukan.
     *
     * @return array{0:string,1:string}
     */
    public static function allowedDateRange(int $backDays = 30, int $forwardDays = 90): array
    {
        return [
            now()->subDays($backDays)->format('Y-m-d'),
            now()->addDays($forwardDays)->format('Y-m-d'),
        ];
    }

    private static function clean(mixed $value): ?string
    {
        if ($value === null || !is_scalar($value)) {
            return null;
        }

        $clean = trim((string) $value);

        return $clean === '' ? null : $clean;
    }
}
