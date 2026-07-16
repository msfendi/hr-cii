<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class EnsureQrScanned
{
    // Sesi hasil scan hanya berlaku beberapa menit, supaya di kios bersama
    // tidak "kebawa" ke orang berikutnya yang pakai device yang sama.
    private const MAX_MINUTES = 5;

    public function handle(Request $request, Closure $next)
    {
        $npk = session('food_order.npk');
        $scannedAt = session('food_order.scanned_at');

        if (!$npk || !$scannedAt || now()->diffInMinutes($scannedAt) > self::MAX_MINUTES) {
            session()->forget(['food_order.npk', 'food_order.nama', 'food_order.scanned_at']);

            return redirect()->route('food-orders.scan')
                ->with('error', 'Silakan scan QR code karyawan terlebih dahulu.');
        }

        return $next($request);
    }
}
