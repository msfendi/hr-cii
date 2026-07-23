<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use App\Support\SqlServerChunk;

class SyncMsSupplierFromSmartit extends Command
{
    protected $signature = 'monitoring:sync-ms-supplier';

    protected $description = 'Sync FULL master data supplier (mon_ms_suppliers) dari smartit.ms_supplier';

    public function handle(): int
    {
        $this->info('Mengambil data ms_supplier dari smartit...');

        $rows = DB::connection('smartit')->table('ms_supplier')->get();

        if ($rows->isEmpty()) {
            $this->warn('Tidak ada data ms_supplier di smartit. Tabel lokal tidak diubah.');
            return self::SUCCESS;
        }

        $now = now();

        $payload = $rows->map(function ($r) use ($now) {
            return [
                'supplier_code'   => $r->supplier_code,
                'supplier_name'   => $r->supplier_name,
                'npwp'            => $r->npwp,
                'phone'           => $r->phone,
                'pic'             => $r->pic,
                'email'           => $r->email,
                'rekening'        => $r->rekening,
                'category'        => $r->category,
                'kode_sub_ap'     => $r->kode_sub_ap,
                'kode_sub_ar'     => $r->kode_sub_ar,
                'negara_id'       => $r->negara_id,
                'supplier_status' => $r->supplier_status,
                'create_by'       => $r->create_by,
                'create_date'     => $r->create_date,
                'modify_by'       => $r->modify_by,
                'modify_date'     => $r->modify_date,
                'ppb'             => $r->ppb,
                'tpb'             => $r->tpb,
                'nib'             => $r->nib,
                'tgl_tpb'         => $r->tgl_tpb,
                'created_at'      => $now,
                'updated_at'      => $now,
            ];
        });

        DB::table('mon_ms_suppliers')->truncate();

        $chunkSize = SqlServerChunk::rows(columnsPerRow: 22);

        foreach ($payload->chunk($chunkSize) as $chunk) {
            DB::table('mon_ms_suppliers')->insert($chunk->all());
        }

        $this->info("Sync selesai: {$rows->count()} supplier disinkron ke mon_ms_suppliers.");

        return self::SUCCESS;
    }
}
