<?php

namespace App\Services\RecruitmentAi;

use App\Models\PelamarDetails;
use App\Models\RecruitmentPosition;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Penyimpan data pendaftar (PELAMAR + pelamar_details).
 *
 * Sengaja dipisah dari RecruitmentFormController (alur lama) supaya:
 *  - alur lama tidak tersentuh / tidak bentrok,
 *  - logic ini bisa dipakai ulang oleh form versi AI maupun form biasa.
 *
 * Berkas dokumen SUDAH tersimpan di disk (diunggah di awal alur), jadi di
 * sini file-nya cukup dipindahkan ke folder final `pelamar/{field}` dengan
 * penamaan yang sama seperti alur lama.
 */
class ApplicantRegistrationService
{
    /**
     * Simpan pendaftar.
     *
     * @param  array  $data  data form (sudah tervalidasi)
     * @param  array<string, array{path:string, name:string}>  $files  dokumen terunggah
     * @return array{pelamar_id:int, files:array<string,string>}
     */
    public function register(array $data, array $files): array
    {
        $namaPelamar = Str::slug($data['nama_lengkap'] ?? 'pelamar', '_');
        $timestamp   = Carbon::now()->format('Ymd-His');

        // ── Pindahkan berkas ke folder final ─────────────────────────────────
        $storedFiles = [];

        try {
            foreach ($files as $field => $file) {
                if (empty($file['path'])) {
                    continue;
                }

                $sourcePath = $file['path'];

                if (! Storage::disk('public')->exists($sourcePath)) {
                    continue;
                }

                $ext      = strtolower(pathinfo($file['name'] ?: $sourcePath, PATHINFO_EXTENSION)) ?: 'bin';
                $filename = "{$field}_{$namaPelamar}_{$timestamp}.{$ext}";
                $target   = "pelamar/{$field}/{$filename}";

                $moved = Storage::disk('public')->move($sourcePath, $target);

                if ($moved === false) {
                    throw new RuntimeException("Gagal memindahkan berkas {$field} ke folder penyimpanan.");
                }

                $storedFiles[$field] = $target;
            }
        } catch (\Throwable $e) {
            // Balikin berkas yang sempat berpindah supaya tidak ada file nyasar.
            $this->deleteFiles($storedFiles);

            throw $e;
        }

        // ── Umur ─────────────────────────────────────────────────────────────
        $umur = null;
        if (! empty($data['tanggal_lahir'])) {
            $diff = Carbon::parse($data['tanggal_lahir'])->diff(Carbon::now());
            $umur = $diff->y . ' Tahun ' . $diff->m . ' Bulan ' . $diff->d . ' Hari';
        }

        // ── Pendidikan terakhir ──────────────────────────────────────────────
        $urutan              = ['SD', 'SMP', 'SMA', 'SMK', 'D1', 'D2', 'D3', 'D4', 'S1', 'S2', 'S3'];
        $education           = $data['education'] ?? [];
        $pendidikanTerakhir  = null;
        $namaSekolah         = null;
        $jurusan             = null;

        if (! empty($education)) {
            $sorted = collect($education)
                ->sortByDesc(fn ($e) => array_search($e['tingkat'] ?? '', $urutan))
                ->first();

            if ($sorted) {
                $pendidikanTerakhir = $sorted['tingkat']  ?? null;
                $namaSekolah        = $sorted['institusi'] ?? null;
                $jurusan            = $sorted['jurusan']   ?? null;
            }
        }

        if (! $pendidikanTerakhir) {
            $pendidikanTerakhir = $data['pendidikan'] ?? null;
        }

        // ── Insert PELAMAR (koneksi cii) ─────────────────────────────────────
        try {
            $recruitmentPos = RecruitmentPosition::where('position', $data['jabatan'] ?? '')
                ->where('dept', $data['department'] ?? '')
                ->first();

            $isStaff = $recruitmentPos ? ($recruitmentPos->is_staff ?? 0) : 0;

            $pelamarId = DB::connection('cii')->table('PELAMAR')->insertGetId([
                'is_staff'         => $isStaff,
                'NAMA'             => strtoupper($data['nama_lengkap'] ?? '-'),
                'NIK'              => $data['nik']              ?? null,
                'NO_KK'            => $data['no_kk']            ?? null,
                'JENIS_KELAMIN'    => strtoupper($data['jenis_kelamin'] ?? '-'),
                'TMPT_LAHIR'       => strtoupper($data['tempat_lahir']  ?? '-'),
                'TGL_LAHIR'        => $data['tanggal_lahir']    ?? null,
                'UMUR'             => $umur ?? '-',
                'STATUS'           => $data['status_pernikahan'] ?? '-',
                'TANGGUNGAN'       => $data['tanggungan']        ?? 0,
                'AGAMA'            => strtoupper($data['agama'] ?? '-'),
                'IBU'              => strtoupper(trim($data['ibu']['nama'] ?? '') ?: '-'),
                'HP'               => $data['nomor_hp']          ?? '-',
                'ALAMAT_LENGKAP'   => strtoupper($data['alamat_asal'] ?? '-'),
                'KABUPATEN'        => strtoupper($data['kab_kota_asal'] ?? '-'),
                'ALAMAT_DOMISILI'  => strtoupper($data['status_domisili_asal'] ?? '-'),
                'PENDIDIKAN'       => strtoupper($pendidikanTerakhir ?? '-'),
                'JURUSAN'          => strtoupper($jurusan ?? '-'),
                'NAMA_SEKOLAH'     => strtoupper(trim($namaSekolah ?? '') ?: '-'),
                'KABUPATEN_SEKOLAH' => '-',
                'TINGGI_BADAN'     => $data['tinggi_badan'] ?? 0,
                'BERAT_BADAN'      => $data['berat_badan']  ?? 0,
                'IS_KONTRAK'       => 'FALSE',
            ]);
        } catch (\Throwable $e) {
            $this->deleteFiles($storedFiles);

            throw new RuntimeException('Gagal menyimpan data pribadi pendaftar: ' . $e->getMessage(), 0, $e);
        }

        // ── Insert pelamar_details ───────────────────────────────────────────
        $detailsPayload = [
            'id_pelamar'         => $pelamarId,

            'nomor_sim'          => $data['sim']          ?? null,
            'warga_negara'       => $data['warga_negara'] ?? null,
            'ikut_kb'            => ($data['kb'] ?? 'Tidak') === 'Ya' ? 1 : 0,
            'bakat_hobby'        => $data['hobby']         ?? null,
            'mode_transportasi'  => $data['transportasi']  ?? null,
            'jabatan'            => $data['jabatan']        ?? null,
            'department'         => $data['department']     ?? null,
            'bpjs_tk'            => $data['bpjs_tk']        ?? null,
            'bpjs_kes'           => $data['bpjs_kes']       ?? null,

            'alamat_skrg'        => $data['alamat_sekarang']         ?? $data['alamat_asal']      ?? null,
            'kabupaten_kota_skrg' => $data['kab_kota_sekarang']       ?? $data['kab_kota_asal']    ?? null,
            'status_domisili'    => $data['status_domisili_sekarang'] ?? $data['status_domisili_asal'] ?? null,
            'nama_ktk_darurat'   => $data['nama_darurat']     ?? null,
            'hubungan'           => $data['hubungan_darurat']  ?? null,
            'no_telp_darurat'    => $data['no_telepon_darurat'] ?? null,

            'pengalaman_kerja'   => ! empty($data['experiences']) ? json_encode($data['experiences']) : null,
            'data_ayah'          => ! empty($data['ayah'])  ? json_encode($data['ayah'])  : null,
            'data_ibu'           => ! empty($data['ibu'])   ? json_encode($data['ibu'])   : null,
            'saudara_kandung'    => ! empty($data['saudara']) ? json_encode($data['saudara']) : null,
            'data_anak'          => ! empty($data['anak'])   ? json_encode($data['anak'])   : null,
            'riwayat_pendidikan' => ! empty($education)      ? json_encode($education)      : null,

            'motivasi'           => $data['motivasi']       ?? null,
            'kegiatan_ekstra'    => $data['kegiatan_ekstra'] ?? null,

            'file_surat_lamaran'   => $storedFiles['surat_lamaran']         ?? null,
            'file_cv'              => $storedFiles['cv']                     ?? null,
            'file_ktp'             => $storedFiles['scan_ktp']               ?? null,
            'file_kk'              => $storedFiles['scan_kk']                ?? null,
            'file_pas_foto'        => $storedFiles['pas_foto']               ?? null,
            'file_ijasah'          => $storedFiles['ijazah']                 ?? null,
            'file_akta_kelahiran'  => $storedFiles['scan_akta_kelahiran']    ?? null,
            'file_skck'            => $storedFiles['scan_skck']              ?? null,
            'file_surat_sehat'     => $storedFiles['scan_blanko_kesehatan']  ?? null,

            'status_apply'       => 'APPLIED',
            'is_test'            => 'FALSE',
            'tgl_test'           => null,
            'tgl_interview'      => null,
            'tgl_kesehatan'      => null,
            'tgl_diterima'       => null,
            'is_interview'       => 'FALSE',
            'is_kesehatan'       => 'FALSE',
        ];

        try {
            PelamarDetails::create($detailsPayload);
        } catch (\Throwable $e) {
            // Rollback: hapus baris PELAMAR + berkas yang sudah dipindahkan.
            try {
                DB::connection('cii')->table('PELAMAR')->where('id', $pelamarId)->delete();
            } catch (\Throwable) {
                // diabaikan
            }

            $this->deleteFiles($storedFiles);

            Log::error('[RecruitmentAi] Gagal insert pelamar_details: ' . $e->getMessage(), [
                'message' => $e->getMessage(),
                'trace'   => $e->getTraceAsString(),
            ]);

            throw new RuntimeException(
                'Gagal menyimpan detail pendaftaran: ' . $e->getMessage(),
                0,
                $e
            );
        }

        return [
            'pelamar_id' => $pelamarId,
            'files'      => $storedFiles,
        ];
    }

    /**
     * Hapus berkas yang gagal disimpan.
     */
    private function deleteFiles(array $paths): void
    {
        foreach ($paths as $path) {
            try {
                Storage::disk('public')->delete($path);
            } catch (\Throwable) {
                // diabaikan
            }
        }
    }
}
