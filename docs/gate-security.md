# Gate Security — Pemeriksaan Dokumen di Pos Security

Halaman tempat petugas security memeriksa dokumen karyawan lewat **scan QR**:
scan pertama mencatat karyawan **KELUAR**, scan berikutnya mencatat **KEMBALI**.

Halaman ini **generik** — tidak terikat pada satu jenis dokumen. Saat ini
melayani Surat Ijin Dinas Luar; dokumen berikutnya (mis. Izin Meninggalkan
Pekerjaan) cukup menambah satu adapter.

## URL

| Halaman | URL |
|---------|-----|
| Login petugas | `/gate-security/login` |
| Scan | `/gate-security` |

Jalur lama `/work-assignment/security` (dan `/login`) masih hidup sebagai
**redirect 301** ke halaman baru, supaya bookmark yang sudah beredar tidak mati.

## Alur

```
Karyawan                     Petugas security
   │                                │
   ├─ buka riwayat pengajuan        │
   ├─ tekan tombol QR ──────────────┤  QR: GATE:izin-dinas:<id>:<token>
   │                                ├─ login NPK (cukup terdaftar di PKWT)
   │                                ├─ scan QR (kamera / scanner USB / ketik)
   │                                ├─ lihat ringkasan + status approval
   │                                └─ "Konfirmasi KELUAR"
   │
   ... karyawan kembali ...
   │
   └─ tunjukkan QR yang sama ────────┴─ scan lagi → "Konfirmasi KEMBALI"
                                       └─ jam_kembali terisi otomatis
```

Aturan yang dipegang:

1. Dokumen hanya boleh diproses kalau approval-nya **sudah lengkap**.
2. Urutan dijaga: `out` dulu, baru `in`.
3. Sekali tercatat **tidak bisa diubah**.
4. Gate `in` mengisi `jam_kembali` sesuai waktu scan.

## Peta File

| File | Peran |
|------|-------|
| `app/Services/GateSecurity/GateDocument.php` | kontrak dokumen (interface) |
| `app/Services/GateSecurity/GateRegistry.php` | daftar doctype yang didukung — **satu-satunya tempat yang tahu jenis dokumen** |
| `app/Services/GateSecurity/GateQr.php` | tulis/baca payload QR |
| `app/Services/GateSecurity/GateOfficer.php` | verifikasi NPK petugas |
| `app/Http/Controllers/GateSecurityController.php` | halaman + endpoint (generik, tak tahu dokumen) |
| `resources/views/gate_security/login.blade.php` | login petugas |
| `resources/views/gate_security/scan.blade.php` | halaman scan (generik) |
| `app/Services/WorkAssignment/WorkAssignmentGateAdapter.php` | adapter untuk Izin Dinas |
| `app/Services/WorkAssignment/WorkAssignmentQr.php` | QR yang ditampilkan ke pemohon |

Logika gate sengaja diletakkan di `app/Services/GateSecurity/` mengikuti pola
repo ini (`app/Services/ApprovalWa/`, `app/Services/WorkAssignment/`), bukan
folder baru langsung di bawah `app/`.

Catatan penyimpanan: hasil gate disimpan di kolom milik dokumennya
(`work_assignment_requests.security_check`), bukan tabel bersama — tiap
dokumen punya siklus hidup sendiri.

## Isi QR

```
GATE:<doctype>:<id>:<token>
GATE:izin-dinas:10009:TSTGATE87e6585afafeebe5
```

Token ikut dibawa supaya kode tidak bisa dipalsukan dengan menebak nomor urut.

QR lama berbentuk `WA:<id>:<token>` (dibuat sebelum ada doctype; `WA` = Work
Assignment) **tetap dibaca** dan dipetakan ke `izin-dinas`, supaya QR yang
sudah terbuka di HP karyawan tidak langsung hangus.

## Menambah jenis dokumen baru

1. Buat adapter: `implements App\Services\GateSecurity\GateDocument`, isi `docType()`,
   `label()`, `find()`, `isReady()`, `stage()`, `summary()`, `gates()`,
   `recordGate()`.
2. Daftarkan di `GateRegistry::DOCUMENTS` — satu baris.
3. Pastikan dokumen menyediakan QR dengan format `GATE:<doctype>:<id>:<token>`.

`GateSecurityController` dan seluruh view **tidak perlu diubah**.

## Kamera

Kamera hanya diizinkan browser pada **HTTPS** atau **localhost**. Kalau halaman
dibuka lewat `http://<IP-LAN>`, `navigator.mediaDevices` disembunyikan
sepenuhnya dan prompt izin **tidak pernah muncul** (bukan ditolak user).

Saat itu terjadi, halaman menampilkan peringatan dan menonaktifkan tombol
kamera. Alternatif yang tetap jalan tanpa HTTPS:

- **input manual** — kolom kode sudah difokuskan otomatis saat halaman dibuka;
- **scanner QR USB** — bekerja seperti keyboard (mengetik kode lalu Enter).

## Menu

`Approval → Security Gate` — dipasang oleh:

```bash
php artisan work-assignment:install-menu
```

Command itu juga **menonaktifkan menu lama** `Security Gate Work Assignment`
(route `work-assignment.security.scan`) supaya tidak muncul dobel di sidebar.