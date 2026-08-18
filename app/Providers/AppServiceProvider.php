<?php

namespace App\Providers;

use App\Models\ActivityLog;
use App\Models\Magang;
use App\Models\PKWT;
use App\Models\User;
use App\Observers\GlobalObserver;
use App\Observers\MagangObserver;
use App\Observers\PkwtObserver;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use ReflectionClass;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        PKWT::observe(PkwtObserver::class);
        Magang::observe(MagangObserver::class);

        // $models = File::allFiles(app_path('Models'));

        // foreach ($models as $model) {

        //     $class = 'App\\Models\\' .
        //         str_replace(
        //             ['/', '.php'],
        //             ['\\', ''],
        //             $model->getRelativePathname()
        //         );

        //     if (!class_exists($class)) {
        //         continue;
        //     }

        //     $reflection = new ReflectionClass($class);

        //     // ✅ SKIP ABSTRACT CLASS
        //     if ($reflection->isAbstract()) {
        //         continue;
        //     }

        //     // ✅ HARUS ELOQUENT MODEL
        //     if ($reflection->isSubclassOf(Model::class)) {
        //         $class::observe(GlobalObserver::class);
        //     }
        // }
        view()->composer('*', function ($view) {
            if (Auth::check()) {
                $roleusers = User::select('users.name', 'users.email', 'users.id', 'model_has_roles.*', 'roles.name as rolename')
                    ->leftJoin('model_has_roles', 'model_has_roles.model_id', '=', 'users.id')
                    ->leftJoin('roles', 'roles.id', '=', 'model_has_roles.role_id')
                    ->where('users.id', '=', Auth::user()->id)
                    ->get();

                View::share(['roleusers' => $roleusers]);
            }
        });
    }
}
