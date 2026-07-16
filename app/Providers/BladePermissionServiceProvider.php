<?php

namespace App\Providers;

use App\Support\RoutePermission;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\ServiceProvider;

/**
 * Daftarkan di config/app.php pada array 'providers':
 *     App\Providers\BladePermissionServiceProvider::class,
 *
 * Atau jika pakai Laravel 11 (bootstrap/app.php):
 *     ->withProviders([App\Providers\BladePermissionServiceProvider::class])
 *
 * ==============================================
 * CARA PAKAI DI BLADE:
 * ==============================================
 *
 * @canRoute('payroll-process.destroy')
 *     <button class="btn btn-danger">Delete</button>
 * @endcanRoute
 *
 * @cannotRoute('payroll.export.rekap')
 *     <span class="text-muted">Tidak ada akses export</span>
 * @endcannotRoute
 *
 * Atau inline (untuk kondisi satu baris):
 * @canRoute('payroll-process.process')
 *     <a href="#">Proses</a>
 * @endcanRoute
 */
class BladePermissionServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        // @canRoute('route.name') ... @endcanRoute
        Blade::directive('canRoute', function (string $expression) {
            return "<?php if(\\App\\Support\\RoutePermission::check({$expression})): ?>";
        });

        Blade::directive('endcanRoute', function () {
            return '<?php endif; ?>';
        });

        // @cannotRoute('route.name') ... @endcannotRoute
        Blade::directive('cannotRoute', function (string $expression) {
            return "<?php if(!\\App\\Support\\RoutePermission::check({$expression})): ?>";
        });

        Blade::directive('endcannotRoute', function () {
            return '<?php endif; ?>';
        });
    }
}
