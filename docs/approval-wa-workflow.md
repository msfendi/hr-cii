# Approval Cuti via WhatsApp — Workflow & Skill Setup

## Arsitektur

```
[leave_requests DB]
       ▲                 │
       │ Update status   │ 1. Cek pending tiap 5 jam
       │                 ▼
[ApprovalWa Services] ◄──── [Artisan Command: approval:check-pending]
       │
       │ 2. Kirim notif ke approver level berikutnya
       ▼
[WA Gateway (Hermes)] ◄──► [Approver WhatsApp]
       │
       │ 3. Approver balas: "APPROVE 123" / "REJECT 123 alasan"
       ▼
[POST /api/approval/webhook]
       │
       ▼
[InboundApprovalProcessor]
  - Validasi nomor HP → NPK → approval_rules
  - Validasi level (sequential)
  - Update DB
  - Kirim konfirmasi balik
```

---

## Komponen Kode

### Service Classes (`app/Services/ApprovalWa/`)

| File | Fungsi |
|------|--------|
| `PhoneFormatter.php` | Normalisasi nomor HP ke format `62xxx`, validasi format Indonesia |
| `LeaveApprovalQuery.php` | Semua query DB: pending requests, next approver, validasi, update |
| `WaMessageFormatter.php` | Format pesan outbound (notif) dan inbound (konfirmasi/error) |
| `WaGateway.php` | Kirim pesan via REST API WA gateway |
| `InboundApprovalProcessor.php` | Parse reply WA, validasi approver, update DB |

### Command (`app/Console/Commands/`)

| File | Artisan Command |
|------|----------------|
| `CheckPendingLeaveApproval.php` | `php8.3 artisan approval:check-pending [--dry-run]` |

### Controller (`app/Http/Controllers/Api/`)

| File | Route |
|------|-------|
| `ApprovalWebhookController.php` | `POST /api/approval/webhook` |

---

## Logika Approval Sequential

```
leave_requests
├── approval_id      → foreign key ke approval_rules (chain/grup approver)
├── approval_level   → total level yang harus approve (misal: 3)
└── approval_progress→ sudah berapa level yang approve (mulai dari 0)

approval_rules
├── rules_id         → ID grup aturan
├── approval_id      → NPK approver
└── level            → urutan level (1, 2, 3, ...)

Alur:
  progress=0, level=3 → notif ke approver level 1
  progress=1, level=3 → notif ke approver level 2
  progress=2, level=3 → notif ke approver level 3
  progress=3, level=3 → semua selesai, status=approved
```

---

## Setup di Server Baru

### 1. Prerequisites

```bash
# PHP 8.3 + sqlsrv
sudo add-apt-repository ppa:ondrej/php -y
sudo apt-get install -y php8.3-cli php8.3-dev php8.3-xml php8.3-mbstring \
  php8.3-zip php8.3-gd php8.3-bcmath php8.3-curl php8.3-intl

# Microsoft ODBC + sqlsrv extension
curl -sSL https://packages.microsoft.com/keys/microsoft.asc | gpg --dearmor | \
  sudo tee /usr/share/keyrings/microsoft-prod.gpg > /dev/null
echo "deb [arch=amd64 signed-by=/usr/share/keyrings/microsoft-prod.gpg] \
  https://packages.microsoft.com/ubuntu/24.04/prod noble main" | \
  sudo tee /etc/apt/sources.list.d/mssql-release.list
sudo apt-get update
sudo ACCEPT_EULA=Y apt-get install -y msodbcsql18 unixodbc-dev
sudo pecl install sqlsrv pdo_sqlsrv
echo "extension=sqlsrv.so" | sudo tee /etc/php/8.3/cli/conf.d/20-sqlsrv.ini
echo "extension=pdo_sqlsrv.so" | sudo tee /etc/php/8.3/cli/conf.d/20-pdo_sqlsrv.ini
```

### 2. Clone & Setup

```bash
git clone -b agents-approval-wa https://github.com/msfendi/hr-cii.git
cd hr-cii
cp .env.example .env
php8.3 artisan key:generate
```

### 3. Configure `.env`

```env
# Database SQL Server
DB_CII_CONNECTION=cii
DB_CII_HOST=192.168.3.55
DB_CII_PORT=13279
DB_CII_DATABASE=CII
DB_CII_USERNAME=hr_app
DB_CII_PASSWORD=Password123!

# WhatsApp Gateway
WA_GATEWAY_URL=http://localhost:3000   # sesuaikan dengan URL gateway Hermes
WA_GATEWAY_TOKEN=                      # optional bearer token
WA_WEBHOOK_SECRET=                     # optional X-WA-Token secret
```

### 4. Install Dependencies

```bash
composer install --no-dev
php8.3 artisan config:clear
php8.3 artisan storage:link
```

### 5. Test Koneksi DB

```bash
php8.3 artisan tinker --execute="DB::connection('cii')->getPdo(); echo 'DB OK';"
```

### 6. Test Dry Run

```bash
php8.3 artisan approval:check-pending --dry-run
```

### 7. Enable Cron

```bash
crontab -e
# Tambah:
* * * * * cd /path/to/hr-cii && php8.3 artisan schedule:run >> /dev/null 2>&1
```

Default interval: **5 jam** (`everyFiveHours()`).  
Ubah di `routes/console.php`:
```php
Schedule::command('approval:check-pending')
    ->everyThirtyMinutes()  // atau: hourly(), everyFiveHours(), dll
```

### 8. Expose Webhook ke WA Gateway

WA gateway perlu bisa POST ke `http://<server>:<port>/api/approval/webhook`.

Jika pakai Hermes gateway lokal, daftarkan webhook URL di konfigurasi gateway:
```
Webhook URL: http://192.168.0.68:8010/api/approval/webhook
```

---

## Format Pesan

### Outbound (Notifikasi ke Approver)

```
╔══════════════════════════╗
🗂 *PENGAJUAN CUTI - PERLU APPROVAL*
╚══════════════════════════╝

📋 *ID Pengajuan:* #859
👤 *Pemohon:* Ahmad (C-00825)
📅 *Tanggal:* 2026-11-15 s/d 2026-11-17 (3 hari)
📝 *Alasan:* Keperluan keluarga
🔢 *Level Approval:* 1 dari 3

━━━━━━━━━━━━━━━━━━━━━━━━━━
Yth. *Budi Santoso*,
Mohon berikan keputusan Anda dengan membalas:

✅ *APPROVE 859*
❌ *REJECT 859 <alasan>*

⚠️ Pastikan ID pengajuan sesuai sebelum membalas.
```

### Inbound (Reply dari Approver)

```
APPROVE 859
REJECT 859 anggaran over budget
```

### Konfirmasi Balik

```
✔️ *Konfirmasi Keputusan*

Pengajuan cuti *#859* telah *✅ DISETUJUI*.
Oleh: Budi Santoso
Waktu: 10/11/2026 15:30

Terima kasih.
```

---

## Nomor HP yang Diizinkan

Approver hanya bisa mengirim keputusan jika nomor HP-nya terdaftar di `PKWT.HP`
dan NPK-nya ada di `approval_rules.approval_id`.

Untuk menambah nomor HP yang diizinkan akses Hermes secara global, konfigurasikan
di whitelist Hermes gateway (akan diinformasikan terpisah).

Format HP yang didukung:
- `08123456789` → `6281234567890` ✅
- `+6281234567890` → `6281234567890` ✅
- `81234567890` → `6281234567890` ✅
- `0812-3456-789` → `6281234567890` ✅ (strip separator)

---

## Hermes Skill

Skill `approval-manager` tersimpan di:
```
~/.hermes/skills/approval-manager/SKILL.md
```

Load skill di Hermes agent:
```
skill_view(name='approval-manager')
```

Trigger otomatis saat prompt mengandung:
- "check pending leave approval"
- "kirim notifikasi approval cuti"
- "proses approval via whatsapp"
- "approve reject pengajuan cuti"

---

## Catatan Keamanan

1. `WA_WEBHOOK_SECRET` — set header `X-WA-Token` di gateway untuk autentikasi webhook
2. Approver divalidasi 2 lapis: (a) nomor HP → NPK lookup, (b) NPK di `approval_rules`
3. Sequential enforcement: tidak bisa skip level
4. Satu request tidak bisa di-approve oleh approver yang salah level
