<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class SalaryApprove extends Model
{
    protected $table = 'salary_approve';

    protected $casts = [
        'progress'    => 'array',
        'approved_at' => 'array',
    ];

    protected $fillable = [
        'id_pelamar',
        'expected_salary',
        'approved_salary',
        'progress',
        'approved_at',
        'status',
        'requested_by',
    ];

    /**
     * PELAMAR ada di koneksi 'cii' (SQL Server) yang terpisah dari koneksi
     * default tempat tabel ini dan `users` berada, jadi tidak bisa pakai
     * belongsTo lintas koneksi. Ambil manual kalau butuh data pelamar live.
     */
    public function pelamar()
    {
        return DB::connection('cii')->table('PELAMAR')->where('ID', $this->id_pelamar)->first();
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }
}