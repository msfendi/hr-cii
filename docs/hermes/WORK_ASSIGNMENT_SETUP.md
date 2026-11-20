# Work Assignment (Izin Dinas) — Setup Produksi

Panduan menyalakan modul **Work Assignment / Surat Ijin Dinas Luar** di server
produksi: approval lewat WhatsApp, QR pengajuan, dan halaman Security Gate.

Ikuti berurutan. Setiap bagian punya perintah verifikasi — jangan lanjut kalau
verifikasinya belum sesuai.

> **Yang perlu dipahami lebih dulu:** modul ini **menumpang** infrastruktur
> approval WhatsApp yang sudah dipakai cuti. Webhook route, script agen, dan
> API token **sama**. Tidak ada route webhook baru, tidak ada token baru.
> Yang baru hanya: 3 migrasi, menu/permission, dan satu cron sweep.

---

## 0. Ringkasan yang baru vs yang dipakai bersama

| Komponen                                                                  | Status          |
| ------------------------------------------------------------------------- | --------------- |
| Tabel `work_assignment_requests` + kolom `attach_files`, `security_check` | **baru**        |
| Menu & permission (Pengajuan, Manage, Approval, Security Gate)            | **baru**        |
| Cron sweep Izin Dinas                                                     | **baru**        |
| Webhook route Hermes (`leave-approval`)                                   | dipakai bersama |
| Script agen (`approval-*.py`)                                             | dipakai bersama |
| `APPROVAL_WEBHOOK_URL`, `APPROVAL_WEBHOOK_SECRET`, `APPROVAL_API_TOKEN`   | dipakai bersama |
| Bridge WhatsApp                                                           | dipakai bersama |

---

## Bagian A — Server Laravel

### A1. Backup dulu

```bash
cd /path/ke/hr-cii
php artisan --version
git rev-parse --short HEAD        # catat commit sebelum deploy
```

Pastikan DB `cii` bisa diakses:

```bash
php artisan tinker --execute="DB::connection('cii')->select('SELECT 1 AS ok');"
```

Kalau ini gagal, **stop** — jangan lanjut ke migrasi.

### A2. Migrasi

Tiga migrasi yang harus jalan:

```
2026_11_11_150000_create_work_assignment_requests_table.php
2026_11_17_000001_add_attach_files_to_work_assignment_requests_table.php
2026_11_17_000002_add_security_check_to_work_assignment_requests_table.php
```

```bash
php artisan migrate --force
```

⚠️ **Kalau `migrate` gagal dengan `insufficient system memory in resource pool
'internal'`** — itu query pemeriksaan ukuran DB bawaan Laravel yang kehabisan
memori di SQL Server, bukan migrasinya. Jalankan satu per satu:

```bash
php artisan migrate --force --path=database/migrations/2026_11_11_150000_create_work_assignment_requests_table.php
php artisan migrate --force --path=database/migrations/2026_11_17_000001_add_attach_files_to_work_assignment_requests_table.php
php artisan migrate --force --path=database/migrations/2026_11_17_000002_add_security_check_to_work_assignment_requests_table.php
```

Kalau masih gagal, tambahkan kolomnya manual lewat SQL Server lalu catat di
tabel `migrations` (lihat lampiran di bagian G).

Verifikasi:

```bash
php artisan tinker --execute="echo implode(', ', Schema::getColumnListing('work_assignment_requests'));"
```

Harus memuat `attach_files` dan `security_check`.

### A3. Menu & permission

```bash
php artisan work-assignment:install-menu
```

Perintah ini **idempotent** — aman diulang. Ia membuat/memperbarui:

- permission: Pengajuan Work Assignment, Manage Work Assignment,
  Approval Work Assignment, Security Gate
- menu: `Leave & Holiday → Manage Work Assignment`,
  `Approval → Work Assignment Approval`, `Approval → Security Gate`
- menonaktifkan menu lama `Security Gate Work Assignment` (route
  `work-assignment.security.scan`) kalau pernah terpasang

Tambahkan ke role tertentu saja bila perlu:

```bash
php artisan work-assignment:install-menu --role=Security
```

Tanpa `--role`, permission diwariskan ke role yang punya "Lihat Leave Recap".

### A4. `.env`

Tidak ada key baru. Pastikan sudah ada (biasanya sudah terisi untuk cuti):

```env
APPROVAL_WEBHOOK_URL=http://<hermes-host>:8644/webhooks/leave-approval
APPROVAL_WEBHOOK_SECRET=<secret-yang-sama-dengan-hermes>
APPROVAL_API_TOKEN=<token-yang-sama-dengan-hermes>
```

Setelah mengubah `.env`:

```bash
php artisan config:clear
php artisan view:clear
```

### A5. Storage lampiran

Lampiran disimpan di disk `public`.

```bash
php artisan storage:link
ls -ld storage/app/public
```

### A6. Verifikasi Laravel

```bash
# Halaman pemohon & gate harus 200
curl -s -o /dev/null -w "%{http_code}\n" https://<domain>/work-assignment/login
curl -s -o /dev/null -w "%{http_code}\n" https://<domain>/gate-security/login

# API approval harus 401 tanpa token, 200 dengan token
curl -s -o /dev/null -w "%{http_code}\n" https://<domain>/api/approval/pending
curl -s -H "Authorization: Bearer $APPROVAL_API_TOKEN" "https://<domain>/api/approval/pending?doc=izin-dinas"
```

Respons terakhir harus JSON dengan `"doc":"izin-dinas"`.

---

## Bagian B — Server Hermes (messaging layer)

### B1. Sinkronkan script

Salin dari repo (folder `docs/hermes/scripts/`) ke `~/.hermes/scripts/`:

```bash
cp docs/hermes/scripts/approval-*.py       ~/.hermes/scripts/
cp docs/hermes/scripts/check-*.sh          ~/.hermes/scripts/
chmod +x ~/.hermes/scripts/approval-*.py ~/.hermes/scripts/check-*.sh
```

Versi script **wajib** yang mendukung `doc` — tanpa itu notifikasi Izin Dinas
tidak akan pernah terkirim (script lama mengabaikan `doc` dan menganggap semua
permintaan sebagai cuti). Cek cepat:

```bash
grep -n "doc" ~/.hermes/scripts/approval-webhook.py | head -5
```

### B2. Config

`~/.hermes/approval-wa.env` (sama seperti cuti, tidak ada key baru):

```env
HR_CII_API_URL=https://hris.chutex.id/
APPROVAL_API_TOKEN=bfcb1e679e4d94bb0cfa25aab2fcd53069e0de671c3d2ddd
WA_BRIDGE_URL=http://127.0.0.1:3000
WA_JITTER_SECONDS=15
WA_MIN_GAP_SECONDS=15
WA_MAX_PER_MINUTE=6
```

> **Keamanan:** jangan menyimpan token asli di file yang ikut git. File
> contoh di repo (`approval-wa.env.example`) hanya untuk placeholder. Kalau
> token pernah ikut ter-commit, **rotasi** tokennya (ganti di kedua sisi) —
> menghapus file tidak menghapus riwayat git.

### B3. Webhook route — tidak perlu baru

Route yang sudah ada (`leave-approval`) dipakai untuk **kedua** dokumen, karena
pembedanya ada di field `doc` yang dikirim Laravel. Perbarui deskripsinya saja
supaya tidak menyesatkan:

```bash
hermes webhook list
```

`subscribe` tidak menimpa route yang sudah ada, jadi untuk memperbarui
deskripsi/secret: hapus dulu, lalu daftarkan ulang dengan secret yang sama.

```bash
hermes webhook remove leave-approval
hermes webhook subscribe leave-approval \
  --description "Laravel trigger: notif WA approval cuti + izin dinas" \
  --script "approval-webhook.py" \
  --deliver local \
  --secret <secret-yang-sama-dengan-Laravel> (bfcb1e679e4d94bb0cfa25aab2fcd53069e0de671c3d2ddd)
```

⚠️ Kalau `--secret` dikosongkan, Hermes membuat secret **baru** — Laravel akan
gagal (signature mismatch). Selalu isi dengan secret yang sama, atau salin
secret baru ke `APPROVAL_WEBHOOK_SECRET` di Laravel.

**Jangan** membuat route baru dengan nama lain; `APPROVAL_WEBHOOK_URL` di
Laravel hanya satu nilai.

### B4. Cron sweep Izin Dinas

```bash
hermes cron create "every 5h" \
  --name "work-assignment-approval-check" \
  --script "check-work-assignment-approval.sh" \
  --no-agent \
  --deliver local
```

`--no-agent` → stdout script dikirim langsung, tanpa LLM (hemat biaya).

**Untuk cuti**, kalau job lama masih memakai `approval-outbound.py` tanpa
`--doc`, ubah ke wrapper-nya:

```bash
hermes cron edit <job-id-cuti> --script "check-leave-approval.sh"
```

> ⚠️ **Kenapa penting:** `approval-outbound.py` **tanpa** `--doc` menyapu cuti
> **dan** izin dinas sekaligus. Kalau job cuti lama dibiarkan begitu sementara
> job Izin Dinas juga jalan, approver bisa menerima pesan yang sama **dua kali**.
> Dua job + dua wrapper ber-`--doc` = tidak ada tumpang tindih.

Verifikasi:

```bash
hermes cron list
python3 ~/.hermes/scripts/check-work-assignment-approval.sh   # jalankan sekali
```

### B5. Verifikasi tanpa mengirim WhatsApp

```bash
# Dry-run: hanya menyusun dan mencetak pesan
python3 ~/.hermes/scripts/approval-outbound.py --doc izin-dinas --dry-run

# Health bridge
curl -s http://127.0.0.1:3000/health
```

`--dry-run` **tidak** mengirim. Tanpa `--dry-run` langsung mengirim.

---

## Bagian C — Security Gate

### C1. Halaman

| Halaman       | URL                    |
| ------------- | ---------------------- |
| Login petugas | `/gate-security/login` |
| Scan          | `/gate-security`       |

Jalur lama `/work-assignment/security` tetap hidup sebagai redirect 301.

### C2. Menu

`Approval → Security Gate` (dipasang oleh `work-assignment:install-menu`).
Berikan permission-nya ke role petugas security di menu Management User, atau:

```bash
php artisan work-assignment:install-menu --role=<nama-role-security>
```

### C3. Kamera — wajib HTTPS

Kamera hanya diizinkan browser pada **HTTPS** atau `localhost`. Kalau petugas
membuka `http://<IP-LAN>`, prompt izin **tidak pernah muncul** (bukan ditolak)
dan tombol kamera dinonaktifkan otomatis oleh halaman.

Karena itu, untuk pos security:

- sajikan aplikasi lewat **HTTPS**, atau
- pakai **scanner QR USB** (bekerja seperti keyboard: mengetik kode lalu Enter
  — kolom input sudah difokuskan otomatis), atau
- input manual.

### C4. Alur uji

1. Karyawan buka `/work-assignment` → tombol **QR** pada pengajuan yang sudah
   disetujui semua level.
2. Petugas buka `/gate-security` → login NPK → scan/ketik kode.
3. Klik **Konfirmasi KELUAR** → tercatat (nama, NPK, waktu).
4. Scan kode yang sama → **Konfirmasi KEMBALI** → `jam_kembali` terisi.

---

## Bagian D — Urutan eksekusi (salin-tempel)

```bash
# ── Laravel ──
cd /path/ke/hr-cii
php artisan tinker --execute="DB::connection('cii')->select('SELECT 1 AS ok');"
php artisan migrate --force
php artisan work-assignment:install-menu
php artisan storage:link
php artisan config:clear && php artisan view:clear

# ── Hermes ──
cp <repo>/docs/hermes/scripts/approval-*.py ~/.hermes/scripts/
cp <repo>/docs/hermes/scripts/check-*.sh    ~/.hermes/scripts/
chmod +x ~/.hermes/scripts/approval-*.py ~/.hermes/scripts/check-*.sh

python3 ~/.hermes/scripts/approval-outbound.py --doc izin-dinas --dry-run

hermes cron create "every 5h" \
  --name "work-assignment-approval-check" \
  --script "check-work-assignment-approval.sh" \
  --no-agent --deliver local
```

---

## Bagian E — Checklist

- [ ] DB `cii` terhubung dari server Laravel
- [ ] Kolom `attach_files` dan `security_check` ada
- [ ] `work-assignment:install-menu` sukses; menu `Security Gate` aktif,
      menu lama nonaktif
- [ ] `/work-assignment/login` → 200
- [ ] `/gate-security/login` → 200
- [ ] `/api/approval/pending?doc=izin-dinas` → 200 + `"doc":"izin-dinas"`
- [ ] `approval-webhook.py` versi yang mendukung `doc` (cek `grep doc`)
- [ ] Webhook route `leave-approval` aktif, secret sama dengan Laravel
- [ ] `approval-outbound.py --doc izin-dinas --dry-run` mencetak pesan, bukan error
- [ ] Job `work-assignment-approval-check` ada dan aktif
- [ ] Job cuti memakai `check-leave-approval.sh` (bukan `approval-outbound.py` polos)
- [ ] Petugas security bisa login & scan (HTTPS atau scanner USB)
- [ ] Uji keluar → kembali pada satu pengajuan sungguhan

---

## Bagian F — Troubleshooting

| Gejala                                                                   | Penyebab                                                                        | Tindakan                                 |
| ------------------------------------------------------------------------ | ------------------------------------------------------------------------------- | ---------------------------------------- |
| Notifikasi Izin Dinas tidak pernah masuk                                 | `approval-webhook.py` versi lama mengabaikan `doc` → Laravel menganggapnya cuti | Salin ulang script, cek `grep doc`       |
| Approver menerima pesan 2×                                               | dua jalur sweep aktif (job lama tanpa `--doc` + job baru)                       | Pakai wrapper ber-`--doc` untuk keduanya |
| `not_eligible` untuk token Izin Dinas                                    | `doc` tidak diteruskan ke `/api/approval/trigger`                               | Cek `approval-webhook.py` / `check-*.sh` |
| Pengajuan ditolak saat submit: "nomor WhatsApp approver belum terdaftar" | `PKWT.HP` approver kosong/tidak valid                                           | Lengkapi nomor di master karyawan        |
| Tombol kamera nonaktif                                                   | halaman dibuka lewat `http://` non-localhost                                    | HTTPS, atau scanner USB/input manual     |
| `migrate` gagal "insufficient system memory"                             | query size-check bawaan Laravel                                                 | jalankan per-file (lihat A2)             |
| QR lama tidak terbaca                                                    | seharusnya terbaca (`WA:` dipetakan ke izin-dinas)                              | cek query di `GateQr::parse`             |

Log yang berguna:

```bash
tail -f /path/ke/hr-cii/storage/logs/laravel.log | grep -iE "approval|izin|workassignment"
```

---

## Bagian G — Lampiran: migrasi manual

Kalau `php artisan migrate` tetap gagal karena memori SQL Server, tambahkan
kolom manual lalu catat migrasinya:

```sql
ALTER TABLE work_assignment_requests ADD attach_files NVARCHAR(MAX) NULL;
ALTER TABLE work_assignment_requests ADD security_check NVARCHAR(MAX) NULL;
```

```bash
php artisan tinker --execute="
DB::table('migrations')->insert([
    'migration' => '2026_11_17_000001_add_attach_files_to_work_assignment_requests_table',
    'batch'     => DB::table('migrations')->max('batch') + 1,
]);
DB::table('migrations')->insert([
    'migration' => '2026_11_17_000002_add_security_check_to_work_assignment_requests_table',
    'batch'     => DB::table('migrations')->max('batch') + 1,
]);
"
```

---

## Bagian H — Rollback

```bash
# Matikan job sweep
hermes cron pause <job-id>

# Kembalikan kode
cd /path/ke/hr-cii && git checkout <commit-sebelum-deploy>
php artisan config:clear && php artisan view:clear
```

Kolom `attach_files` dan `security_check` boleh dibiarkan (nullable, tidak
mengganggu kode lama). Jangan drop tabel `work_assignment_requests` kalau sudah
ada data pengajuan sungguhan.
