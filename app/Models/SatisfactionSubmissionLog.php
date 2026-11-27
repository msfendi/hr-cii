<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Satu baris = satu kiriman kuesioner yang berhasil dari sebuah PERANGKAT.
 * Dipakai hanya untuk penjaga anti-spam (bukan untuk analisis jawaban).
 *
 * Kunci pemblokiran = device_hash (ID perangkat dari cookie pertama-pihak).
 * Tidak ada IP di sini: IP tidak layak dipakai sebagai identitas perangkat.
 */
class SatisfactionSubmissionLog extends Model
{
    protected $table = 'satisfaction_submission_logs';

    protected $fillable = [
        'device_hash',
        'user_agent',
        'period_key',
        'submission_id',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];
}