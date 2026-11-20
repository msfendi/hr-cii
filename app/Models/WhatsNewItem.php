<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Satu banner "What's New".
 *
 * Dipakai bersama oleh slider HRIS v1 (layout v1) dan HRIS v2
 * (hris_v2/layout.blade.php) supaya admin hanya mengisi data sekali.
 */
class WhatsNewItem extends Model
{
    protected $table = 'whats_new_items';

    protected $fillable = [
        'title',
        'summary',
        'media_type',
        'media_path',
        'media_url',
        'button_label',
        'button_route',
        'button_url',
        'sort_order',
        'is_active',
        'starts_at',
        'ends_at',
        'created_by',
    ];

    protected $casts = [
        'is_active'  => 'boolean',
        'sort_order' => 'integer',
        'starts_at'  => 'datetime',
        'ends_at'    => 'datetime',
    ];

    /**
     * Banner yang boleh tampil sekarang: aktif + sudah masuk periode +
     * belum lewat periode.
     *
     * Urutan modal: nilai urut TERBESAR dianggap paling baru dan tampil lebih
     * dulu, karena banner baru otomatis mendapat sort_order terbesar
     * (lihat WhatsNewController::create()). Bila nilainya sama, id terbesar
     * menang sehingga banner yang lebih baru tetap di depan.
     */
    public function scopeAktif($query)
    {
        $sekarang = now();

        return $query
            ->where('is_active', true)
            ->where(function ($q) use ($sekarang) {
                $q->whereNull('starts_at')->orWhere('starts_at', '<=', $sekarang);
            })
            ->where(function ($q) use ($sekarang) {
                $q->whereNull('ends_at')->orWhere('ends_at', '>=', $sekarang);
            })
            ->orderByDesc('sort_order')
            ->orderByDesc('id');
    }

    /**
     * Jenis media yang harus dirender: gambar | video | youtube | embed |
     * video-url | none.
     */
    public function jenisMedia(): string
    {
        $tipe = strtolower((string) $this->media_type);

        if ($tipe === 'none' || $tipe === '') {
            return 'none';
        }

        if ($this->embedUrl() !== null) {
            return str_contains((string) $this->embedUrl(), 'player.vimeo.com') ? 'embed' : 'youtube';
        }

        if ($this->media_path) {
            return $tipe === 'video' ? 'video' : 'gambar';
        }

        if ($this->media_url) {
            return $tipe === 'video' ? 'video-url' : 'gambar';
        }

        return 'none';
    }

    /**
     * Apakah banner ini memuat media video (berkas unggahan maupun tautan).
     *
     * Dipakai menu admin untuk memutuskan tombol QR ditampilkan: QR hanya
     * bermakna bila ada video yang bisa ditonton.
     */
    public function punyaVideo(): bool
    {
        return in_array($this->jenisMedia(), ['video', 'video-url', 'youtube', 'embed'], true);
    }

    /**
     * Apakah banner sedang tayang sekarang: status aktif DAN berada di dalam
     * periode tayang.
     *
     * Berbeda dari scopeAktif() yang membangun query, method ini menguji satu
     * model yang sudah dimuat. Dipakai untuk menjaga akses video: begitu
     * banner dinonaktifkan atau periodenya lewat, video tidak boleh dibuka
     * lagi lewat QR maupun URL langsung.
     */
    public function sedangAktif(): bool
    {
        if (! $this->is_active) {
            return false;
        }

        $sekarang = now();

        if ($this->starts_at && $this->starts_at->greaterThan($sekarang)) {
            return false;
        }

        if ($this->ends_at && $this->ends_at->lessThan($sekarang)) {
            return false;
        }

        return true;
    }

    /** URL halaman tonton video (target QR code). */
    public function urlTonton(): ?string
    {
        if (! $this->punyaVideo() || ! $this->exists) {
            return null;
        }

        return route('whats-new.watch', ['id' => $this->getKey()]);
    }

    /** URL media siap pakai (berkas unggahan atau URL eksternal). */
    public function mediaSrc(): ?string
    {
        if ($this->media_path) {
            // Disajikan lewat route whats-new.media: instalasi ini tidak
            // memakai symlink public/storage, jadi /storage/... tidak bisa
            // diandalkan untuk berkas yang baru diunggah.
            try {
                if (\Illuminate\Support\Facades\Route::has('admin.whats-new.media')) {
                    return route('admin.whats-new.media', ['berkas' => basename($this->media_path)]);
                }
                if (\Illuminate\Support\Facades\Route::has('whats-new.media')) {
                    return route('whats-new.media', ['berkas' => basename($this->media_path)]);
                }
                return asset('storage/' . $this->media_path);
            } catch (\Throwable $e) {
                return asset('storage/' . $this->media_path);
            }
        }

        return $this->media_url ?: null;
    }

    /**
     * URL embed untuk YouTube/Vimeo. Null bila bukan layanan embed
     * (mis. file MP4 atau URL video langsung).
     */
    public function embedUrl(): ?string
    {
        $url = trim((string) $this->media_url);

        if ($url === '') {
            return null;
        }

        if (preg_match('~youtu\.be/([A-Za-z0-9_-]{6,})~', $url, $m)) {
            return 'https://www.youtube.com/embed/' . $m[1];
        }

        if (preg_match('~(?:youtube\.com/(?:watch\?(?:.*&)?v=|embed/|shorts/|live/))([A-Za-z0-9_-]{6,})~', $url, $m)) {
            return 'https://www.youtube.com/embed/' . $m[1];
        }

        if (preg_match('~vimeo\.com/(?:video/)?(\d{5,})~', $url, $m)) {
            return 'https://player.vimeo.com/video/' . $m[1];
        }

        return null;
    }

    /**
     * Tujuan tombol: route bernama lebih dulu, lalu URL eksternal.
     * Null bila admin tidak mengisi tombol.
     */
    public function tombolHref(): ?string
    {
        if ($this->button_route && Route::has($this->button_route)) {
            try {
                return route($this->button_route);
            } catch (\Throwable $e) {
                // Route butuh parameter: jatuh ke URL bila ada.
            }
        }

        return $this->button_url ?: null;
    }

    public function tombolEksternal(): bool
    {
        $href = $this->tombolHref();

        return $href !== null && ! Str::startsWith($href, url('/'));
    }

    /** Hapus berkas media milik banner ini (dipanggil saat update/hapus). */
    public function hapusMedia(): void
    {
        if ($this->media_path && Storage::disk('public')->exists($this->media_path)) {
            Storage::disk('public')->delete($this->media_path);
        }
    }
}