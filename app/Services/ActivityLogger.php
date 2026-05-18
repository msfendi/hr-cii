<?php

namespace App\Services;

use App\Models\ActivityLog;

class ActivityLogger
{
    public static function log(array $data)
    {
        if (self::shouldSkip()) {
            return;
        }

        ActivityLog::create([
            'user_id' => auth()->id(),
            'action'  => $data['action'] ?? null,
            'model'   => $data['model'] ?? null,
            'description' => $data['description'] ?? null,
            'old_data' => $data['old'] ?? null,
            'new_data' => $data['new'] ?? null,
            'method' => request()->method(),
            'url'    => request()->fullUrl(),
            'ip'     => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);
    }

    private static function shouldSkip(): bool
    {
        if (app()->runningInConsole()) {
            return true;
        }

        if (!request()) {
            return true;
        }

        // ✅ skip ajax read request
        if (request()->ajax() && request()->isMethod('GET')) {
            return true;
        }

        // ✅ skip GET request
        if (request()->isMethod('GET')) {
            return true;
        }

        // ✅ skip specific routes
        $excludedRoutes = [
            'kunjungan.get-data',
        ];

        if (request()->routeIs($excludedRoutes)) {
            return true;
        }

        // ✅ skip specific paths
        $excludedPaths = [
            'broadcasting/auth',
            'api/notifications*',
        ];

        foreach ($excludedPaths as $path) {
            if (request()->is($path)) {
                return true;
            }
        }

        return false;
    }
}
