<?php

namespace App\Helpers;

use Carbon\Carbon;

class PdfPassword
{
    public static function generate($type, $period)
    {
        $date = Carbon::parse($period);

        // 0126 (bulan+tahun)
        $periodCode = $date->format('my');

        $prefix = [
            'all'        => '1325',
            'staff'      => '0513',
            'nonstaff'   => '1000',
            'sewing'     => '2000',
            'nonsewing'  => '3000',
        ];

        return ($prefix[$type] ?? '0000') . $periodCode;
    }
}
