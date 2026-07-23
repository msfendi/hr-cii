<?php

namespace App\Console\Commands;

use App\Support\SqlServerChunk;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SyncMsBarangFromSmartit extends Command
{
    /**
     * php artisan monitoring:sync-ms-barang
     *
     * Master data barang (bukan transaksi bertanggal), jadi tidak ada opsi
     * --year/--from/--to seperti sync lain -- selalu tarik ULANG SEMUA baris
     * ms_barang dari smartit: tabel lokal dikosongkan dulu, baru diisi ulang
     * (bukan upsert), supaya barang yang sudah tidak ada/berubah di smartit
     * ikut konsisten dengan sumbernya.
     */
    protected $signature = 'monitoring:sync-ms-barang';

    protected $description = 'Sinkronisasi master data barang (ms_barang) dari smartit ke tabel mon_ms_barangs';

    public function handle(): int
    {
        $this->info('Mengambil master data barang (ms_barang) dari smartit ...');

        // barang_code kosong/spasi (baris "Void" upload) dibuang -- bukan
        // barang riil, cuma sampah data dari proses upload di smartit.
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
            // Master data -- hapus dulu SEMUA baris lama, baru insert ulang
            // dari smartit. Bukan upsert, supaya barang yang sudah tidak ada
            // lagi di smartit (dihapus/diganti kodenya) tidak nyangkut di
            // tabel lokal.
            DB::table('mon_ms_barangs')->delete();

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

        $this->info("Selesai. {$inserted} baris master barang diinsert ulang ke mon_ms_barangs (data lama dihapus lebih dulu).");
        return self::SUCCESS;
    }
}
