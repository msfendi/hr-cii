<?php

if (!function_exists('penyebut')) {

    function penyebut($nilai)
    {
        $nilai = abs($nilai);

        $huruf = [
            "",
            "Satu",
            "Dua",
            "Tiga",
            "Empat",
            "Lima",
            "Enam",
            "Tujuh",
            "Delapan",
            "Sembilan",
            "Sepuluh",
            "Sebelas"
        ];

        if ($nilai < 12)
            return " " . $huruf[$nilai];

        elseif ($nilai < 20)
            return penyebut($nilai - 10) . " Belas";

        elseif ($nilai < 100)
            return penyebut($nilai / 10) . " Puluh" . penyebut($nilai % 10);

        elseif ($nilai < 200)
            return " Seratus" . penyebut($nilai - 100);

        elseif ($nilai < 1000)
            return penyebut($nilai / 100) . " Ratus" . penyebut($nilai % 100);

        elseif ($nilai < 2000)
            return " Seribu" . penyebut($nilai - 1000);

        elseif ($nilai < 1000000)
            return penyebut($nilai / 1000) . " Ribu" . penyebut($nilai % 1000);

        elseif ($nilai < 1000000000)
            return penyebut($nilai / 1000000) . " Juta" . penyebut($nilai % 1000000);

        elseif ($nilai < 1000000000000)
            return penyebut($nilai / 1000000000) . " Milyar" . penyebut($nilai % 1000000000);

        return "";
    }
}

if (!function_exists('terbilang')) {

    function terbilang($nilai)
    {
        return trim(penyebut($nilai)) . " Rupiah";
    }
}
