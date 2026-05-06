<?php

namespace App\Imports;

use App\Models\Pelamar;
use Carbon\Carbon;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithStartRow;

class PelamarImport implements ToModel, WithStartRow
{
    /**
     * @param array $row
     *
     * @return \Illuminate\Database\Eloquent\Model|null
     */
    public function startRow(): int
    {
        return 2;
    }

    public function model(array $row)
    {
        // Skip empty rows or rows without NPK
        if (!isset($row[1]) || trim((string)$row[1]) === '') {
            return null;
        }

        // Update NIK index to 19 after shifting
        $existingPelamar = Pelamar::where('NIK', $row[19])->first();

        // if ($existingPelamar != null && $existingPelamar->IS_KONTRAK == 'TRUE') {
        //     dd('Pelamar sudah pernah diimport');
        // }

        // Safe Date Parsing
        try {
            $tgl_lahir = Carbon::instance(\PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject($row[5]));
            $tmk = Carbon::instance(\PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject($row[6]));

            $diff = $tgl_lahir->diff($tmk);
            $umur_string = $diff->y . ' Tahun ' . $diff->m . ' Bulan ' . $diff->d . ' Hari';
        } catch (\Throwable $e) {
            // Fallback or default if date parsing fails
            $umur_string = null;
            $tgl_lahir = null;
            $tmk = null;
        }

        return new Pelamar([
            'NPK' => $row[1],
            'NAMA' => $row[2],
            'JENIS_KELAMIN' => $row[3],
            'TMPT_LAHIR' => $row[4] ?? '',
            'TGL_LAHIR' => $tgl_lahir ? $tgl_lahir->format('Y-m-d') : null,
            'TMK' => $tmk ? $tmk->format('Y-m-d') : null,
            'UMUR' => $umur_string ?? '',
            'ALAMAT_LENGKAP' => $row[8] ?? '',
            'KABUPATEN' => $row[9] ?? '',
            'ALAMAT_DOMISILI' => $row[10] ?? '',
            'PENDIDIKAN' => $row[11] ?? '',
            'NAMA_SEKOLAH' => $row[12] ?? '',
            'KABUPATEN_SEKOLAH' => $row[13] ?? '',
            'JURUSAN' => $row[14] ?? '',
            'TINGGI_BADAN' => $row[15] ?? 0,
            'BERAT_BADAN' => $row[16] ?? 0,
            'HP' => $row[17] ?? '',
            'AGAMA' => $row[18] ?? '',
            'NIK' => $row[19] ?? '',
            'NO_KK' => $row[20] ?? '',
            'IBU' => $row[21] ?? '',
            'STATUS' => $row[22] ?? '',
            'TANGGUNGAN' => $row[23] ?? '',
            'IS_KONTRAK' => 'FALSE'
        ]);
    }
}
