<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserSignature extends Model
{
    // PERBAIKAN V3 (2026-11-05): connection 'sqlsrv' di-comment-out pada
    // config/database.php sehingga pemanggilannya melempar
    // "Database connection [sqlsrv] not configured."
    // Tabel signatures berada di database CII, sama seperti tabel users.
    protected $connection = 'cii';
    protected $table      = 'signatures';

    protected $fillable = [
        'user_id',
        'signature_img',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }
}
