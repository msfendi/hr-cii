<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

class Event extends Model
{
    protected $table = 'events';

    protected $fillable = [
        'nama_event',
        'tanggal_event',
        'waktu_event',
        'lokasi_event',
        'dress_code',
        'detail_event',
        'view_folder',
        'jumlah_hadir',
        'jumlah_tidak_hadir',
        'is_active',
    ];

    protected $casts = [
        'tanggal_event'      => 'date',
        'jumlah_hadir'       => 'integer',
        'jumlah_tidak_hadir' => 'integer',
        'is_active'          => 'boolean',
    ];

    private const HARI = [
        'Sunday' => 'Minggu',
        'Monday' => 'Senin',
        'Tuesday' => 'Selasa',
        'Wednesday' => 'Rabu',
        'Thursday' => 'Kamis',
        'Friday' => 'Jumat',
        'Saturday' => 'Sabtu',
    ];

    private const BULAN = [
        1 => 'Januari',
        2 => 'Februari',
        3 => 'Maret',
        4 => 'April',
        5 => 'Mei',
        6 => 'Juni',
        7 => 'Juli',
        8 => 'Agustus',
        9 => 'September',
        10 => 'Oktober',
        11 => 'November',
        12 => 'Desember',
    ];

    public function invitations()
    {
        return $this->hasMany(EventInvitation::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Dihitung LANGSUNG dari tabel event_invitations, bukan dari kolom
     * fisik "jumlah_hadir". Ini sengaja meng-override akses ke atribut
     * tersebut (walau kolomnya masih ada di DB) supaya angkanya selalu
     * akurat, termasuk kalau ada baris event_invitations yang dihapus/
     * diubah manual langsung lewat database (bukan lewat aplikasi).
     */
    public function getJumlahHadirAttribute(): int
    {
        return $this->invitations()->where('status', 'hadir')->count();
    }

    /**
     * Sama seperti getJumlahHadirAttribute(), untuk status "tidak_hadir".
     */
    public function getJumlahTidakHadirAttribute(): int
    {
        return $this->invitations()->where('status', 'tidak_hadir')->count();
    }

    /**
     * "Senin, 17 Agustus 2026" -> ditulis manual (tidak bergantung ke
     * locale server / ext-intl yang belum tentu ter-set ke id_ID).
     */
    public function getTanggalDisplayAttribute(): string
    {
        if (!$this->tanggal_event) {
            return '-';
        }

        $date  = Carbon::parse($this->tanggal_event);
        $hari  = self::HARI[$date->format('l')] ?? $date->format('l');
        $bulan = self::BULAN[(int) $date->format('n')] ?? $date->format('F');

        return "{$hari}, {$date->format('d')} {$bulan} {$date->format('Y')}";
    }

    /**
     * Gabungan tanggal_event + jam yang berhasil ditebak dari waktu_event
     * (mis. "08.00 WIB - Selesai" -> 08:00:00), dipakai sebagai target
     * countdown di blade. Fallback ke jam 08:00 kalau tidak ketemu pola jam.
     */
    public function getCountdownTargetAttribute(): string
    {
        $jam = '08:00:00';

        if ($this->waktu_event && preg_match('/(\d{1,2})[.:](\d{2})/', $this->waktu_event, $m)) {
            $jam = str_pad($m[1], 2, '0', STR_PAD_LEFT) . ':' . $m[2] . ':00';
        }

        $tanggal = $this->tanggal_event
            ? Carbon::parse($this->tanggal_event)->format('Y-m-d')
            : now()->format('Y-m-d');

        return "{$tanggal} {$jam}";
    }

    public function getTotalRespondenAttribute(): int
    {
        return $this->jumlah_hadir + $this->jumlah_tidak_hadir;
    }
}
