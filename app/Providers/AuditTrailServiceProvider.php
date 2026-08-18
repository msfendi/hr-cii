<?php

namespace App\Providers;

use App\Services\AuditTrailService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

/**
 * Provider ini yang membuat audit trail berjalan otomatis di SELURUH aplikasi
 * tanpa perlu menambahkan trait atau baris kode apapun di model/controller lain.
 *
 * Cara daftar:
 * - Laravel 11+: tambahkan App\Providers\AuditTrailServiceProvider::class
 *   ke array di bootstrap/providers.php
 * - Laravel 10 ke bawah: tambahkan ke array 'providers' di config/app.php
 */
class AuditTrailServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Event::listen('eloquent.created: *', function ($eventName, array $data) {
            $this->handle('created', $data);
        });

        Event::listen('eloquent.updated: *', function ($eventName, array $data) {
            $this->handle('updated', $data);
        });

        Event::listen('eloquent.deleted: *', function ($eventName, array $data) {
            $this->handle('deleted', $data);
        });
    }

    protected function handle(string $event, array $data): void
    {
        $model = $data[0] ?? null;

        if (! $model instanceof Model) {
            return;
        }

        // Kalau yang berubah cuma updated_at (mis. dari touch()), skip -> tidak ada perubahan berarti
        if ($event === 'updated') {
            $changedKeys = array_diff(array_keys($model->getChanges()), ['updated_at']);
            if (empty($changedKeys)) {
                return;
            }
        }

        AuditTrailService::record($event, $model);
    }
}
