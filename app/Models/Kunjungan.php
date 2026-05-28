<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Kunjungan extends Model
{
    use HasFactory;

    protected $connection = 'cii';
    protected $table = 'kunjungans';
    protected $fillable = [
        'NPK',
        'tanggal_kunjungan',
        'jam_masuk',
        'jam_selesai',
        'keluhan',
        'diagnosa',
        'catatan_dokter',
        'tindakan',
        'status',
        'dokter_id',
        'no_antrian',
        'nama',
        'dept'
    ];

    protected $casts = [
        'tanggal_kunjungan' => 'date',
    ];

    public function resepObats()
    {
        return $this->hasMany(ResepObat::class);
    }

    public function users()
    {
        return $this->belongsTo(User::class, 'dokter_id');
    }
}
