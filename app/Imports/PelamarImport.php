<?php

namespace App\Imports;

use App\Models\Pelamar;
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
        $existingPelamar = Pelamar::where('NIK', $row[18])->first();

        if ($existingPelamar) {
            return null;
        }

        return new Pelamar([
            'NPK' => $row[0],
            'NAMA' => $row[1],
            'JENIS_KELAMIN' => $row[2],
            'TMPT_LAHIR' => $row[3],
            'TMK' => $row[4],
            'TGL_LAHIR' => $row[5],
            'UMUR' => $row[6],
            'ALAMAT_LENGKAP' => $row[7],
            'KABUPATEN' => $row[8],
            'ALAMAT DOMISILI' => $row[9],
            'PENDIDIKAN' => $row[10],
            'NAMA_SEKOLAH' => $row[11],
            'KABUPATEN_SEKOLAH' => $row[12],
            'JURUSAN' => $row[13],
            'TINGGI_BADAN' => $row[14],
            'BERAT_BADAN' => $row[15],
            'HP' => $row[16],
            'AGAMA' => $row[17],
            'NIK' => $row[18],
            'NO_KK' => $row[19],
            'IBU' => $row[20],
            'STATUS' => $row[21],
            'TANGGUNGAN' => $row[22],
            'IS_KONTRAK' => 'FALSE'
        ]);
    }
}
