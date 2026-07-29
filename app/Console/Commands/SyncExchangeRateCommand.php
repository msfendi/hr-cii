<?php

namespace App\Console\Commands;

use App\Services\BiExchangeRateService;
use Illuminate\Console\Command;

class SyncExchangeRateCommand extends Command
{
    protected $signature = 'exchange-rate:sync';

    protected $description = 'Sync rilis kurs USD ke IDR terbaru dari Bank Indonesia (Kurs Transaksi BI)';

    public function handle(BiExchangeRateService $service): int
    {
        $result = $service->syncLatest();

        if ($result) {
            $this->info("Kurs {$result->rate_date->format('Y-m-d')} tersimpan. Jual: {$result->kurs_jual} | Beli: {$result->kurs_beli}");
            return self::SUCCESS;
        }

        $this->warn('Gagal mengambil kurs dari Bank Indonesia (kemungkinan struktur halaman berubah, atau lagi tidak bisa diakses). Cek log aplikasi.');
        return self::FAILURE;
    }
}
