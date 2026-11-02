<?php

namespace App\Console\Commands;

use App\Models\CardMenuSetting;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Diagnosa & perbaiki pengaturan Card Menu HRIS.
 *
 * Dipakai ketika toggle di halaman "Pengaturan Card Menu HRIS" sudah di-on/off
 * tetapi card di halaman HRIS (/) tidak berubah sama sekali.
 */
class CardMenuDoctor extends Command
{
    protected $signature = 'card-menu:doctor {--fix : Terapkan perbaikan otomatis}';

    protected $description = 'Diagnosa & perbaiki pengaturan card menu HRIS (toggle tidak berefek)';

    public function handle(): int
    {
        $this->line('=== DIAGNOSA CARD MENU HRIS ===');

        // 1. Koneksi & database
        $default = config('database.default');
        $this->line('Koneksi default : ' . $default);
        $this->line('Database        : ' . (string) config("database.connections.{$default}.database"));

        try {
            DB::connection()->getPdo();
            $this->info('[OK] Koneksi database berhasil');
        } catch (\Throwable $e) {
            $this->error('[GAGAL] Koneksi database: ' . $e->getMessage());
            $this->line('-> Toggle tidak akan tersimpan ke DB. Perbaiki koneksi dulu.');

            return self::FAILURE;
        }

        // 2. Keberadaan tabel
        $hasTable = Schema::hasTable('card_menu_settings');
        $this->line('Tabel card_menu_settings : ' . ($hasTable ? 'ADA' : 'TIDAK ADA'));

        if (!$hasTable) {
            $this->error('[GAGAL] Tabel belum dibuat -> jalankan: php artisan migrate --force');

            return self::FAILURE;
        }

        // 3. Jumlah baris
        $total = DB::table('card_menu_settings')->count();
        $this->line('Jumlah baris    : ' . $total);

        if ($total === 0) {
            $this->error('[GAGAL] Tabel kosong. visibleCards() akan selalu memakai default hardcoded,');
            $this->warn('        sehingga toggle TIDAK akan pernah berpengaruh.');

            if ($this->option('fix')) {
                $this->seedDefaults();
            } else {
                $this->line('-> Jalankan dengan --fix untuk mengisi data awal.');

                return self::FAILURE;
            }
        }

        // 4. Tampilkan isi
        $this->newLine();
        $this->line('--- Isi tabel ---');
        $rows = DB::table('card_menu_settings')->orderBy('order')->get();
        $this->table(
            ['id', 'key', 'name', 'is_visible', 'order'],
            $rows->map(fn ($r) => [
                $r->id, $r->key, $r->name,
                ((int) $r->is_visible) ? 'ON' : 'OFF',
                $r->order,
            ])->all()
        );

        // 5. Hasil pembacaan oleh aplikasi (yang dipakai hris.blade.php)
        CardMenuSetting::tableExists(true);
        $visible = CardMenuSetting::visibleCards();
        $this->newLine();
        $this->line('--- Card yang TAMPIL di halaman HRIS ---');
        if (empty($visible)) {
            $this->warn('(kosong) — halaman HRIS akan menampilkan "Belum ada menu yang ditampilkan."');
        } else {
            foreach ($visible as $c) {
                $this->line('  [ON] ' . $c['key'] . '  -> ' . $c['name']);
            }
        }

        // 6. Cek file fallback yang bisa menimpa nilai DB
        $filePath = CardMenuSetting::storagePath();
        $this->newLine();
        $this->line('File fallback   : ' . $filePath);
        if (is_file($filePath)) {
            $this->line('Isi file        : ' . (string) file_get_contents($filePath));
            $this->warn('[PERHATIAN] File fallback ada. Pada versi lama file ini MENANG atas DB');
            $this->warn('            sehingga bisa membuat toggle seolah tidak berefek.');

            $this->line('-> File ini sekarang hanya cadangan (DB yang menang). Boleh dihapus.');
        } else {
            $this->line('(tidak ada)');
        }

        // 7. Cek cache
        $this->newLine();
        $this->line('Cache driver    : ' . config('cache.default'));
        $this->line('Config cached   : ' . (is_file(base_path('bootstrap/cache/config.php')) ? 'YA' : 'TIDAK'));
        $this->line('Route cached    : ' . (is_file(base_path('bootstrap/cache/routes-v7.php')) ? 'YA' : 'TIDAK'));

        $this->newLine();
        if ($this->option('fix')) {
            $this->seedDefaults();

            if (is_file($filePath)) {
                @unlink($filePath);
                $this->info('[FIX] File fallback dihapus (DB menjadi satu-satunya sumber).');
            }

            CardMenuSetting::tableExists(true);
            $this->info('[FIX] Selesai. Jalankan: php artisan optimize:clear');
        }

        $this->newLine();
        $this->info('Diagnosa selesai.');

        return self::SUCCESS;
    }

    private function seedDefaults(): void
    {
        $now = now();

        foreach (CardMenuSetting::defaultCards() as $d) {
            $exists = DB::table('card_menu_settings')->where('key', $d['key'])->exists();
            if ($exists) {
                continue;
            }

            DB::table('card_menu_settings')->insert([
                'key'        => $d['key'],
                'name'       => $d['name'],
                'route'      => $d['route'],
                'icon'       => $d['icon'],
                'color'      => $d['color'],
                'is_visible' => $d['is_visible'] ? 1 : 0,
                'order'      => $d['order'],
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            $this->line('  + seed: ' . $d['key']);
        }
    }
}
