<?php

namespace App\Http\Middleware;

use Illuminate\Cookie\Middleware\EncryptCookies as Middleware;

class EncryptCookies extends Middleware
{
    /**
     * The names of the cookies that should not be encrypted.
     *
     * satisfaction_device menyimpan token acak (64 hex) tanpa data pribadi dan
     * dipakai apa adanya sebagai kunci anti-spam kuesioner; tidak perlu
     * dienkripsi supaya nilainya stabil dan bisa dibaca lintas request.
     *
     * @var array<int, string>
     */
    protected $except = [
        'satisfaction_device',
    ];
}
