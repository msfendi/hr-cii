<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;

class ActivityLogController extends Controller
{
    public function index()
    {
        $logs = ActivityLog::with('user')
            ->latest()
            ->limit(1000) // penting supaya tidak berat
            ->get();
        // dd($logs[0]);

        return view('activity_logs.index', compact('logs'));
    }
}
