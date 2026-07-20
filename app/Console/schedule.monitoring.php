<?php

/**
 * Laravel 11+: tempel ke routes/console.php
 * Laravel 10 ke bawah: tempel ke method schedule() di app/Console/Kernel.php
 * (untuk Kernel.php pakai $schedule->command(...) seperti biasa, bukan Schedule::command)
 */

use Illuminate\Support\Facades\Schedule;

Schedule::command('monitoring:sync-bom')->hourly()->withoutOverlapping();
Schedule::command('monitoring:sync-po')->hourly()->withoutOverlapping();
