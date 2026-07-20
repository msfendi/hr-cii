<?php

namespace App\Support;

class SqlServerChunk
{
    /**
     * SQL Server (sqlsrv/pdo_sqlsrv) menolak query dengan lebih dari 2100
     * parameter terikat (bound parameters) dalam satu statement -- termasuk
     * saat Laravel meng-compile insert/upsert banyak baris sekaligus jadi
     * satu query (setiap kolom di setiap baris = 1 parameter).
     *
     * Dipakai untuk menentukan berapa baris yang aman dimasukkan dalam satu
     * array_chunk() sebelum insert/upsert, berdasarkan jumlah kolom per baris.
     *
     * Diberi margin aman (default batas efektif 1800, bukan 2100 mentah)
     * karena beberapa grammar (mis. MERGE untuk upsert) bisa menambah
     * parameter ekstra di klausa MATCHED/NOT MATCHED.
     */
    public static function rows(int $columnsPerRow, int $safeLimit = 1800): int
    {
        if ($columnsPerRow < 1) {
            return 1;
        }

        return max(1, intdiv($safeLimit, $columnsPerRow));
    }
}
