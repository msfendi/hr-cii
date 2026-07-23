<?php

namespace App\Console\Commands;

use App\Support\SqlServerChunk;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SyncMsNegaraFromSmartit extends Command
{
    protected $signature = 'monitoring:sync-ms-negara';

    protected $description = 'Sync FULL master data negara (mon_ms_negaras) dari smartit.ms_negara';

    public function handle(): int
    {
        $this->info('Mengambil data ms_negara dari smartit...');

        $rows = DB::connection('smartit')->table('ms_negara')->get();

        if ($rows->isEmpty()) {
            $this->warn('Tidak ada data ms_negara di smartit. Tabel lokal tidak diubah.');
            return self::SUCCESS;
        }

        $now = now();

        $payload = $rows->map(function ($r) use ($now) {
            return [
                'negara_code' => $r->negara_code,
                'negara_name' => $r->negara_name,
                'create_by'   => $r->create_by,
                'create_date' => $r->create_date,
                'modify_by'   => $r->modify_by,
                'modify_date' => $r->modify_date,
                'created_at'  => $now,
                'updated_at'  => $now,
            ];
        });

        DB::table('mon_ms_negaras')->truncate();

        $chunkSize = SqlServerChunk::rows(columnsPerRow: 8);

        foreach ($payload->chunk($chunkSize) as $chunk) {
            DB::table('mon_ms_negaras')->insert($chunk->all());
        }

        $this->info("Sync selesai: {$rows->count()} negara disinkron ke mon_ms_negaras.");

        return self::SUCCESS;
    }
}
