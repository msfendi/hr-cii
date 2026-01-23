<?php

namespace App\Services;

use App\Models\Pkwt;
use App\Models\Magang;
use App\Models\Rekap;
use Carbon\Carbon;

class RekapService
{
    /**
     * Update rekap untuk bulan dan tahun yang sedang berjalan
     */
    public static function updateRekapBulanBerjalan()
    {
        $now = Carbon::now();
        $bulan = $now->month;
        $tahun = $now->year;
        
        // Hitung total PKWT yang masih aktif
        $countPkwt = PKWT::where('TKK', null)->count();
        
        // Hitung total Magang yang masih aktif
        $countMagang = Magang::where('TKK', null)->count();

        Rekap::updateOrCreate(
            [
                'PKWT' => $countPkwt,
                'MAGANG' => $countMagang,
                'BULAN' => $bulan,
                'TAHUN' => $tahun,
            ]
        );
    }
    
    /**
     * Update rekap untuk bulan dan tahun tertentu (jika diperlukan)
     */
    public static function updateRekap($bulan, $tahun)
    {
        $countPkwt = PKWT::where('TKK', null)->count();
        $countMagang = Magang::where('TKK', null)->count();

        Rekap::updateOrCreate(
            [
                'PKWT' => $countPkwt,
                'MAGANG' => $countMagang,
                'BULAN' => $bulan,
                'TAHUN' => $tahun,
            ]
        );
    }
}