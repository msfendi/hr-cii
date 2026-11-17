<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Helper DEV untuk login cepat saat menguji halaman admin Work Assignment.
 *
 * KHATIAN: route ini HANYA aktif kalau APP_ENV=local (atau APP_DEBUG=true).
 * Di production route ini tidak terdaftar sama sekali.
 *
 * Dipakai: /_dev/login-as?npk=C-00827
 */
class DevLoginController extends Controller
{
    public function __construct()
    {
        // Guard: jangan pernah jalan di production.
        $this->middleware(function ($request, $next) {
            $env = config('app.env');

            if (!in_array($env, ['local', 'testing'], true) && !config('app.debug')) {
                abort(404);
            }

            return $next($request);
        });
    }

    public function loginAs(Request $request)
    {
        $npk = trim((string) $request->query('npk'));

        if ($npk === '') {
            abort(400, 'Parameter npk wajib diisi.');
        }

        $user = User::where('npk', $npk)->first();

        if (!$user) {
            abort(404, "User dengan NPK {$npk} tidak ditemukan.");
        }

        Auth::login($user);
        $request->session()->regenerate();

        return response()->json([
            'ok'   => true,
            'user' => [
                'id'   => $user->id,
                'npk'  => $user->npk,
                'name' => $user->name,
            ],
        ]);
    }
}
