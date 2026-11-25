<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;
use Illuminate\Support\Facades\Log;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     */
    protected function schedule(Schedule $schedule): void
    {
        // $schedule->command('inspire')->hourly();
        $schedule->command('rekap:generate-harian')->everyFiveMinutes();
        $schedule->command('leave:generate-daily')->dailyAt('00:01')->appendOutputTo(storage_path('logs/leave-generate-daily.log'));

        $schedule->command('monitoring:sync-bom')->hourly()->withoutOverlapping();
        $schedule->command('monitoring:sync-po')->hourly()->withoutOverlapping();


        // Jalankan command check kontrak setiap hari jam 8 pagi
        $schedule->command('contract:check-expiring --days=5')
            ->daily()
            ->at('08:00')
            ->timezone('Asia/Jakarta')
            ->name('check-contract-expiring')
            ->withoutOverlapping()
            ->onFailure(function () {
                // Silakan tambahkan log atau alert jika gagal
                Log::error('Command contract:check-expiring failed');
            })
            ->onSuccess(function () {
                Log::info('Command contract:check-expiring executed successfully');
            });

        // Optional: Jalankan juga di jam 12 siang
        $schedule->command('contract:check-expiring --days=5')
            ->dailyAt('12:00')
            ->timezone('Asia/Jakarta');

        // Optional: Archive old notifications setiap minggu
        $schedule->call(function () {
            NotificationsContract::where('created_at', '<', now()->subDays(30))
                ->where('status', 'read')
                ->update(['status' => 'archived']);
        })->weekly()->sundays()->at('23:00');

        // Cek pending leave approval tiap 5 jam, kirim notif WA ke approver
        // Interval bisa diubah: everyThirtyMinutes(), hourly(), everyFiveHours(), dll.
        $schedule->command('approval:check-pending')
            ->cron('0 */5 * * *')
            ->withoutOverlapping()
            ->timezone('Asia/Jakarta')
            ->appendOutputTo(storage_path('logs/approval-wa.log'));

        // Tutup hari: kalau sampai 17:00 karyawan (Izin Dinas / Izin Meninggalkan)
        // belum tercatat kembali oleh Security, jam_kembali diisi 17:00 supaya
        // rekap absensi & potongan payroll tetap terbentuk.
        $schedule->command('izin:auto-fill-jam-kembali --time=17:00')
            ->dailyAt('17:00')
            ->timezone('Asia/Jakarta')
            ->withoutOverlapping()
            ->name('izin-auto-fill-jam-kembali')
            ->onFailure(function () {
                Log::error('Command izin:auto-fill-jam-kembali failed');
            })
            ->appendOutputTo(storage_path('logs/izin-auto-fill-jam-kembali.log'));
    }

    /**
     * Register the commands for the application.
     */
    protected function commands(): void
    {
        $this->load(__DIR__ . '/Commands');

        require base_path('routes/console.php');
    }
}
