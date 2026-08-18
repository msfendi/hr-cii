<?php

namespace App\Http\Middleware;

use Closure;
use App\Services\ActivityLogger;

class ActivityLogMiddleware
{
    public function handle($request, Closure $next)
    {
        $response = $next($request);

        // ActivityLogger::log([
        //     'action' => 'request',
        //     'description' => $request->method() . ' ' . $request->path(),
        // ]);

        return $response;
    }
}
