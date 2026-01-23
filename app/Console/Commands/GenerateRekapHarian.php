<?php

namespace App\Console\Commands;

use App\Services\RekapService;
use Carbon\Carbon;
use Illuminate\Console\Command;

class GenerateRekapHarian extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:generate-rekap-harian';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $now = Carbon::now();
        
        $this->info("Generating rekap untuk bulan {$now->month} tahun {$now->year}...");
        
        RekapService::updateRekapBulanBerjalan();
        
        $this->info("Rekap berhasil digenerate!");
        
        return 0;
    }
}
