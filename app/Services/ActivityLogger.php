<?php

namespace App\Services;

use App\Models\ActivityLog;

class ActivityLogger
{
    public static function log(array $data)
    {
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
}
