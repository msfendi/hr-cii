<?php

namespace App\Console\Commands;

use App\Support\SqlServerChunk;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SyncMsBarangFromSmartit extends Command
{
    protected $signature = 'monitoring:sync-ms-barang';

    protected $description = 'Sinkronisasi FULL master data barang (ms_barang) dari smartit ke tabel mon_ms_barangs';

    public function handle(): int
    {
        $this->info('Mengambil master data barang (ms_barang) dari smartit ...');

        $sql = <<<SQL
            SELECT
                barang_code,
                barang_name,
                satuan_code,
                barang_category,
                header_code,
                hs_code,
                kode_sub_ap,
                kode_sub_ar,
                barang_status,
                create_by,
                create_date,
                modify_by,
                modify_date,
                satuan_hs,
                konversi,
                kode
            FROM ms_barang
            WHERE barang_code IS NOT NULL AND LTRIM(RTRIM(barang_code)) <> ''
        SQL;

        $rows = DB::connection('smartit')->select($sql);

        $this->info('Baris diterima dari smartit: ' . count($rows));

        $now = now();
        $inserted = 0;

        $chunkSize = SqlServerChunk::rows(columnsPerRow: 16);

        DB::transaction(function () use ($rows, $chunkSize, $now, &$inserted) {
            DB::table('mon_ms_barangs')->truncate();

            foreach (array_chunk($rows, $chunkSize) as $chunk) {
                $data = array_map(function ($r) use ($now) {
                    return [
                        'barang_code'     => trim($r->barang_code),
                        'barang_name'     => $r->barang_name,
                        'satuan_code'     => $r->satuan_code,
                        'barang_category' => $r->barang_category,
                        'header_code'     => $r->header_code,
                        'hs_code'         => $r->hs_code,
                        'kode_sub_ap'     => $r->kode_sub_ap,
                        'kode_sub_ar'     => $r->kode_sub_ar,
                        'barang_status'   => $r->barang_status,
                        'create_by'       => $r->create_by,
                        'create_date'     => $r->create_date,
                        'modify_by'       => $r->modify_by,
                        'modify_date'     => $r->modify_date,
                        'satuan_hs'       => $r->satuan_hs,
                        'konversi'        => $r->konversi,
                        'kode'            => $r->kode,
                        'updated_at'      => $now,
                        'created_at'      => $now,
                    ];
                }, $chunk);

                DB::table('mon_ms_barangs')->insert($data);
                $inserted += count($data);
            }
        });

        $this->info("Selesai. {$inserted} baris master barang diinsert ulang ke mon_ms_barangs (data lama dihapus).");

        return self::SUCCESS;
    }
}
