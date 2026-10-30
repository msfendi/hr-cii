<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware: hanya boleh diakses jika sudah login lewat sesi face recognition.
 */
class FaceAttendanceAuth
{
    public function handle(Request $request, Closure $next): Response
    {
        if (!$request->session()->get('face_recognition_auth')) {
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Sesi face recognition tidak valid atau telah kadaluarsa.',
                ], 403);
            }
            return redirect()->route('face.attendance.login');
        }

        return $next($request);
    }
}
