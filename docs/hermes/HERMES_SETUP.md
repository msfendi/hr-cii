# Approval Cuti via WhatsApp — Paket Deploy Hermes Agent

Paket ini berisi **3 commit** di branch `agents-approval-wa` + artefak Hermes
(skill + cron script) supaya bisa di-deploy ke server Hermes manapun.

---

## Daftar File yang Perlu Disiapkan di Server Hermes Baru

| # | File | Sumber | Tujuan di Server Baru |
|---|------|--------|-----------------------|
| 1 | `docs/hermes/skill/approval-manager.SKILL.md` | repo | `~/.hermes/skills/approval-manager/SKILL.md` |
| 2 | `docs/hermes/scripts/check-leave-approval.sh` | repo | `~/.hermes/scripts/check-leave-approval.sh` |
| 3 | `docs/hermes/HERMES_SETUP.md` | repo | panduan setup (file ini) |
| 4 | Cron job | dibuat via CLI | `hermes cron create ...` |

Kode Laravel (branch `agents-approval-wa`) **tetap di repo** — itu execution engine
yang dipanggil oleh script cron dan oleh Hermes saat memproses inbound.

---

## Ringkasan Arsitektur

```
                    OUTBOUND (Hermes cron, tiap 5 jam)
                    ───────────────────────────────────
  [Hermes cron job: leave-approval-check]
            │
            ▼
  [~/.hermes/scripts/check-leave-approval.sh]
            │  php8.3 artisan approval:check-pending
            ▼
  [LeaveApprovalQuery::getPendingForNotification()]
            │  - filter sequential (level N butuh level <N approved)
            │  - cek status approved/rejected di chain token
            ▼
  [WaGateway::send()] ──POST http://localhost:3000/send──► [WA Bridge]
            │                                               │
            ▼                                               ▼
      log                                    [Approver WhatsApp]
                                                          │
                    INBOUND (Hermes WA gateway)         │ balas /approve 849
                    ────────────────────────────────      │       /tolak 849 alasan
                                                          ▼
                                              [Hermes WA Gateway]
                                                          │
                                                          ▼
                                       [Load skill: approval-manager]
                                                          │
                                                          ▼
                                       [InboundApprovalProcessor::handle()]
                                            - parse /approve, /tolak
                                            - phone → NPK (PKWT.HP)
                                            - validasi approval_id + sequential
                                            - update DB status
                                                          │
                                                          ▼
                                            [WaGateway::send() konfirmasi]
```

---

## Skema DB yang Benar

**Penting:** satu pengajuan cuti menghasilkan **N row** di `leave_requests` —
satu row per approver dalam chain. Semua row satu pengajuan punya `token` yang sama.

| Kolom | Isi | Arti |
|-------|-----|------|
| `id` | auto | Primary key row ini |
| `token` | string random | Pengenal pengajuan (pengSATUAN pengajuan) |
| `NPK` | NPK pemohon | Yang mengajukan cuti |
| `approval_id` | NPK approver | **Approver yang bertanggung jawab untuk row ini** |
| `approval_level` | int | Level approver ini dalam chain (1, 2, 3, ...) |
| `approval_progress` | int | Selalu `1` saat dibuat; tetap `1` sampai diproses |
| `status` | pending/approved/rejected | Status keputusan row ini |
| `void` | 'false' | Flag void (bukan NULL) |

Tabel pendukung:

| Tabel | Dipakai untuk |
|-------|---------------|
| `approval_rules` | Daftar NPK yang punya hak jadi approver (`approval_id` + `level` + `rules_id`) |
| `PKWT` | Data karyawan: `NPK`, `NAMA`, `HP` (sumber nomor WhatsApp) |

### Aturan Sequential

```
Row level 1 pending  → notifikasi dikirim ke NPK level 1
Row level 2 pending  → notifikasi dikirim ke NPK level 2 HANYA jika row level 1 = approved
Row level 3 pending  → notifikasi dikirim ke NPK level 3 HANYA jika row level 1 & 2 = approved
```

**Rejected menghentikan chain:** jika row level N di-reject, seluruh row level > N
di chain yang sama **tidak akan menerima notifikasi** (walau statusnya masih `pending` di DB).

Implementasi: `LeaveApprovalQuery::getPendingForNotification()` — group by `token`,
lalu cek `statusMap[token][1..N-1] === 'approved'` untuk semua level sebelumnya.

---

## Deploy di Server Hermes Baru

### Step 1 — Clone repo + install Laravel deps

```bash
git clone -b agents-approval-wa https://github.com/msfendi/hr-cii.git
cd hr-cii
composer install --no-dev
cp .env.example .env
php8.3 artisan key:generate
```

### Step 2 — Install PHP + sqlsrv extension

```bash
sudo add-apt-repository ppa:ondrej/php -y
sudo apt-get update
sudo apt-get install -y php8.3-cli php8.3-dev php8.3-xml php8.3-mbstring \
  php8.3-zip php8.3-gd php8.3-bcmath php8.3-curl php8.3-intl

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

### Step 3 — Configure `.env`

```env
# Database SQL Server (harus SQL Auth, bukan Windows Auth)
DB_CII_CONNECTION=cii
DB_CII_HOST=192.168.3.55
DB_CII_PORT=13279
DB_CII_DATABASE=CII
DB_CII_USERNAME=hr_app
DB_CII_PASSWORD=<password>

# WhatsApp Gateway (Hermes WA bridge, default port 3000)
WA_GATEWAY_URL=http://localhost:3000
WA_GATEWAY_TOKEN=
WA_WEBHOOK_SECRET=
WA_BOT_NUMBER=6287731665679
```

Lalu: `php8.3 artisan config:clear && php8.3 artisan storage:link`

### Step 4 — Verifikasi koneksi DB

```bash
cd /path/to/hr-cii
php8.3 artisan tinker --execute="DB::connection('cii')->getPdo(); echo 'DB OK';"
```

### Step 5 — Install Hermes skill

```bash
mkdir -p ~/.hermes/skills/approval-manager
cp docs/hermes/skill/approval-manager.SKILL.md ~/.hermes/skills/approval-manager/SKILL.md
```

Atau via Hermes tooling (dari repo hr-cii):

```bash
cp /home/hermes/workspace/hr-cii/docs/hermes/skill/approval-manager.SKILL.md \
   ~/.hermes/skills/approval-manager/SKILL.md
```

### Step 6 — Install cron script

```bash
mkdir -p ~/.hermes/scripts
cp docs/hermes/scripts/check-leave-approval.sh ~/.hermes/scripts/
chmod +x ~/.hermes/scripts/check-leave-approval.sh
```

Edit `HR_CII_PATH` di script jika path repo berbeda:

```bash
#!/bin/bash
# Hermes cron script: cek pending leave approval dan kirim notif WA
HR_CII_PATH="/path/to/hr-cii"          # ← SESUAIKAN
cd "$HR_CII_PATH" || exit 1
php8.3 artisan approval:check-pending 2>&1
```

### Step 7 — Register Hermes cron job

```bash
hermes cron create "every 5h" \
  --name "leave-approval-check" \
  --script "check-leave-approval.sh" \
  --no-agent \
  --deliver "whatsapp:<nomor_admin>" \
  --workdir "/path/to/hr-cii"
```

`--no-agent` → script stdout dikirim langsung, tanpa LLM. Logic sudah di PHP.

Cek: `hermes cron list`
Ubah interval: `hermes cron edit 0a23a91b534f`
Pause: `hermes cron pause 0a23a91b534f` | Resume: `hermes cron resume 0a23a91b534f`

### Step 8 — Inbound WA

Hermes WA gateway menerima pesan masuk → agent auto-load skill `approval-manager`
(lihat SKILL.md bagian "Trigger Pattern") → jalankan:

```bash
cd /path/to/hr-cii
php8.3 artisan tinker --execute="
  use App\Services\ApprovalWa\InboundApprovalProcessor;
  \$phone = '<nomor_pengirim>';
  echo InboundApprovalProcessor::handle(\$phone, '<pesan>');
"
```

Lalu Hermes kirim balasan konfirmasi via WA bridge:

```bash
curl -X POST http://localhost:3000/send \
  -H "Content-Type: application/json" \
  -d '{"chatId":"<62xxx>@s.whatsapp.net","message":"<balasan>"}'
```

Atau pakai `WaGateway::send()`.

### Step 9 — Test (TANPA kirim pesan!)

```bash
cd /path/to/hr-cii
php8.3 artisan approval:check-pending --dry-run
```

`--dry-run` hanya print pesan, **tidak mengirim**. Selalu pakai ini sebelum live run.

---

## Format Pesan

### Outbound (Notifikasi ke Approver)

```
╔══════════════════════════╗
🗂 *PENGAJUAN CUTI - PERLU APPROVAL*
╚══════════════════════════╝

📋 *ID Pengajuan:* #859
👤 *Pemohon:* C-00825
📅 *Tanggal:* 2026-10-29 s/d 2026-10-30 (2 hari)
📝 *Alasan:* Acara Gereja
🔢 *Level Approval:* 2

━━━━━━━━━━━━━━━━━━━━━━━━━━
Yth. *RAKHMAT BUDIYONO*,
Silakan berikan keputusan Anda:

✅ *Setujui* → https://wa.me/6287731665679?text=%2Fapprove+859
❌ *Tolak* → https://wa.me/6287731665679?text=%2Ftolak+859+%3Calasan%3E

Atau balas langsung:
• */approve 859*
• */tolak 859 <alasan>*

⚠️ Pastikan ID pengajuan sesuai sebelum membalas.
```

Approver tap link → WA terbuka dengan teks sudah terisi → tinggal kirim.

### Inbound — Format yang Diterima

```
/approve 849
/approve #849
APPROVE 849
/tolak 849 anggaran over budget
REJECT 849 anggaran over budget
```

Regex: `/^(?:APPROVE|\/approve)\s+#?(\d+)$/i` dan
`/^(?:REJECT|\/tolak)\s+#?(\d+)(?:\s+(.+))?$/i`

### Konfirmasi Balik

```
✔️ *Konfirmasi Keputusan*

Pengajuan cuti *#859* telah *✅ DISETUJUI*.
Oleh: RAKHMAT BUDIYONO
Waktu: 10/11/2026 15:30 WIB

Terima kasih.
```

---

## Normalisasi Nomor HP

`PhoneFormatter::normalize()` — sumber: `PKWT.HP`

| Input | Output |
|-------|--------|
| `081234567890` | `6281234567890` ✅ |
| `+6281234567890` | `6281234567890` ✅ |
| `6281234567890` | `6281234567890` ✅ |
| `81234567890` | `6281234567890` ✅ |
| `0812-3456-7890` | `6281234567890` ✅ (separator dihapus) |
| `12345` | `null` ⚠️ dilewati + di-log |

Validasi: `/^628[0-9]{8,12}$/`

Nomor invalid → request di-skip, di-log sebagai warning, tidak dikirim.

---

## Keamanan

1. **Otorisasi 2 lapis** — nomor HP → lookup `PKWT.HP` → cek NPK ada di `approval_rules.approval_id`
2. **Sequential enforcement** — approver hanya bisa memutuskan row yang `approval_id`-nya dia
3. **Anti salah-ID** — NPK approver harus match persis `leave_requests.approval_id` row tersebut
4. **Whitelist inbound** — nomor yang tidak terdaftar di `PKWT.HP` sebagai approver → ditolak
5. `WA_WEBHOOK_SECRET` — header `X-WA-Token` untuk autentikasi endpoint webhook

---

## Troubleshooting

| Gejala | Cek |
|--------|-----|
| Cron jalan tapi "No pending requests" | Filter sequential terlalu ketat? Cek status row level sebelumnya |
| "Failed to send" | `WA_GATEWAY_URL` benar? Bridge health: `curl http://localhost:3000/health` |
| "invalid/missing phone" | `PKWT.HP` kosong untuk NPK itu — isi di master karyawan |
| Approver unauthorized | `PKWT.HP` ≠ format, atau NPK tidak ada di `approval_rules` |
| Notifikasi ke orang salah | Cek `approval_id` di row — harus NPK approver, bukan rules_id |
| Timeout query | `PKWT` sangat besar & tanpa index — query dipecah (sudah di `LeaveApprovalQuery`) |
| SSPI error | SQL Server masih Windows Auth mode — ubah ke mixed mode + buat SQL login |

---

## Catatan Keamanan Penting

> Command `approval:check-pending` (tanpa `--dry-run`) **langsung mengirim pesan WA**.
> Selalu jalankan `--dry-run` dulu untuk verifikasi. Kron Hermes memakai mode ini,
> jadi，要是 cron terpicu otomatis, pesan WILL terkirim.
