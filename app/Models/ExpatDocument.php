<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class ExpatDocument extends Model
{
    use HasFactory;

    protected $table = 'expat_documents';

    protected $fillable = [
        'npk',
        'document_type',
        'file_name',
        'file_path',
        'file_size',
        'notes',
        'uploaded_by',
    ];

    protected $casts = [
        'file_size' => 'integer',
    ];

    protected $appends = [
        'file_url',
    ];

    /**
     * Relasi ke master expat (npk -> npk).
     * ExpatMaster berada di koneksi default yang sama, sehingga
     * relasi standar bisa digunakan.
     */
    public function expat()
    {
        return $this->belongsTo(ExpatMaster::class, 'npk', 'npk');
    }

    /**
     * URL publik untuk mengakses/menampilkan file (disk: public).
     */
    public function getFileUrlAttribute(): ?string
    {
        if (!$this->file_path) {
            return null;
        }

        return Storage::disk('public')->url($this->file_path);
    }

    /**
     * Ukuran file yang sudah diformat, mis. "1.2 MB".
     */
    public function getFileSizeFormattedAttribute(): string
    {
        $bytes = (int) $this->file_size;

        if ($bytes <= 0) {
            return '-';
        }

        $units = ['B', 'KB', 'MB', 'GB'];
        $i = floor(log($bytes, 1024));

        return round($bytes / (1024 ** $i), 2) . ' ' . $units[$i];
    }
}
