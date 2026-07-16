<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HealthTest extends Model
{
    protected $fillable = [
        'nik',
        'cacat',
        'buta_warna',
        'visus_mata',
        'visus_mata_od',
        'visus_mata_os',
        'tinggi',
        'berat',
        'abdoment',
        'gigi',
        'cor_pulmo',
        'tht',
        'extreme',
        'tekanan_darah',
        'respirasi',
        'denyut',
        'suhu',
        'paru',
        'hepatitis',
        'jantung',
        'thypoid',
        'alergi',
        'ashma',
        'lain',
        'kesimpulan',
        'remark'
    ];
}
