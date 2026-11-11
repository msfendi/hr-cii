# Approval Cuti via WhatsApp — Paket Deploy Hermes (Opsi C)

Arsitektur **decoupling**: Laravel = business logic, Hermes = messaging layer.

```
Laravel (server business logic)          Hermes (server messaging)
┌──────────────────────────────┐         ┌─────────────────────────────┐
│ submit pengajuan             │         │ webhook :8644               │
│   → POST webhook trigger ────┼────────►│   → approval-webhook.py     │
│ approve level N              │         │   → GET /approval/trigger   │
│   → POST webhook trigger ────┼────────►│   → throttle → WA bridge    │
│                              │         │                             │
│ GET  /api/approval/pending   │◄────────│ cron 5 jam (safety net)     │
│ POST /api/approval/trigger   │◄────────│   → approval-outbound.py    │
│ POST /api/approval/decision  │◄────────│ inbound WA (approver)       │
│                              │         │   → approval-inbound.py     │
└──────────────────────────────┘         └─────────────────────────────┘
```

Laravel tidak pernah mengirim WhatsApp sendiri. Hermes tidak pernah query DB
dan tidak pernah memutuskan validitas approval.

---

## File yang Perlu Disiapkan di Server Hermes Baru

| # | File (dari repo) | Tujuan di server Hermes |
|---|------------------|-------------------------|
| 1 | `docs/hermes/skill/approval-manager.SKILL.md` | `~/.hermes/skills/approval-manager/SKILL.md` |
| 2 | `docs/hermes/scripts/approval-webhook.py` | `~/.hermes/scripts/` |
| 3 | `docs/hermes/scripts/approval-outbound.py` | `~/.hermes/scripts/` |
| 4 | `docs/hermes/scripts/approval-inbound.py` | `~/.hermes/scripts/` |
| 5 | `docs/hermes/scripts/approval_env.py` | `~/.hermes/scripts/` |
| 6 | `docs/hermes/scripts/approval-wa.env.example` | `~/.hermes/approval-wa.env` (isi nilai asli) |
| 7 | `docs/hermes/HERMES_SETUP.md` | panduan ini |

Semua script wajib ada di `~/.hermes/scripts/` — Hermes menolak path di luar itu.

---

## Bagian A — Setup di Server Laravel (business logic)

### A1. Konfigurasi `.env`

```env
# Database SQL Server (SQL Auth, bukan Windows Auth)
DB_CII_CONNECTION=cii
DB_CII_HOST=192.168.3.55
DB_CII_PORT=13279
DB_CII_DATABASE=CII
DB_CII_USERNAME=hr_app
DB_CII_PASSWORD=<password>
DB_CII_LOGIN_TIMEOUT=10

# API yang dipanggil messaging layer (Hermes) untuk ambil data & kirim keputusan
APPROVAL_API_TOKEN=<token-acak-panjang>
APPROVAL_API_ALLOWED_IPS=192.168.0.68        # IP server Hermes (opsional)

# Hermes webhook — Laravel POST ke sini saat ada pengajuan/approve
APPROVAL_WEBHOOK_URL=http://192.168.0.68:8644/webhooks/leave-approval
APPROVAL_WEBHOOK_SECRET=<secret-dari-hermes-webhook-subscribe>
```

Generate token:
```bash
php8.3 -r "echo bin2hex(random_bytes(24)), PHP_EOL;"
```

### A2. Verifikasi

```bash
php8.3 artisan config:clear
php8.3 artisan route:list --path=api/approval

# Tanpa token harus 401
curl -s -o /dev/null -w "%{http_code}\n" http://127.0.0.1:8010/api/approval/pending

# Dengan token harus 200 (butuh DB hidup)
curl -s -H "Authorization: Bearer <token>" http://127.0.0.1:8010/api/approval/pending | head -c 300
```

### A3. Endpoint yang tersedia

| Method | Path | Auth | Fungsi |
|--------|------|------|--------|
| GET | `/api/approval/pending` | Bearer | list pengajuan eligible + pesan siap kirim |
| POST | `/api/approval/trigger` | Bearer | payload 1 pengajuan (`{token}`) |
| POST | `/api/approval/decision` | Bearer | proses keputusan (`{sender, message}`) |

---

## Bagian B — Setup di Server Hermes (messaging layer)

### B1. Install skill + scripts

```bash
mkdir -p ~/.hermes/skills/approval-manager ~/.hermes/scripts

cp <repo>/docs/hermes/skill/approval-manager.SKILL.md ~/.hermes/skills/approval-manager/SKILL.md
cp <repo>/docs/hermes/scripts/approval-webhook.py   ~/.hermes/scripts/
cp <repo>/docs/hermes/scripts/approval-outbound.py  ~/.hermes/scripts/
cp <repo>/docs/hermes/scripts/approval-inbound.py   ~/.hermes/scripts/
cp <repo>/docs/hermes/scripts/approval_env.py       ~/.hermes/scripts/
chmod +x ~/.hermes/scripts/approval-*.py
```

### B2. Config messaging layer

```bash
cp <repo>/docs/hermes/scripts/approval-wa.env.example ~/.hermes/approval-wa.env
chmod 600 ~/.hermes/approval-wa.env
```

Isi `~/.hermes/approval-wa.env`:

```env
HR_CII_API_URL=http://192.168.0.68:8010
APPROVAL_API_TOKEN=<harus sama dengan .env Laravel>
WA_BRIDGE_URL=http://127.0.0.1:3000
WA_JITTER_SECONDS=15
WA_MIN_GAP_SECONDS=15
WA_MAX_PER_MINUTE=6
```

### B3. Enable webhook platform

```bash
hermes config set platforms.webhook.enabled true
hermes config set platforms.webhook.extra.port 8644
hermes config set platforms.webhook.extra.secret <secret-acak>

export XDG_RUNTIME_DIR=/run/user/$(id -u)
systemctl --user daemon-reload
systemctl --user restart hermes-gateway
curl -s http://localhost:8644/health
# → {"status": "ok", "platform": "webhook"}
```

### B4. Subscribe route webhook

```bash
hermes webhook subscribe leave-approval \
  --description "Laravel trigger: kirim notif WA ke approver cuti" \
  --script "approval-webhook.py" \
  --deliver local \
  --secret <secret-sama-dengan-B3>
```

Catat URL + secret yang dikembalikan → masukkan ke `.env` Laravel
(`APPROVAL_WEBHOOK_URL`, `APPROVAL_WEBHOOK_SECRET`).

### B5. Cron safety net (opsional tapi disarankan)

```bash
hermes cron create "every 5h" \
  --name "leave-approval-sweep" \
  --script "approval-outbound.py" \
  --no-agent \
  --deliver local
```

`--no-agent` → script stdout dikirim langsung, tanpa LLM.

### B6. Verifikasi

```bash
# Tanpa kirim WA
python3 ~/.hermes/scripts/approval-outbound.py --dry-run

# Health bridge WA
curl -s http://127.0.0.1:3000/health
```

---

## Bagian C — Inbound (approver balas via WA)

Hermes WA gateway menerima pesan → agent load skill `approval-manager` →
jalankan:

```bash
python3 ~/.hermes/scripts/approval-inbound.py \
  --phone "<nomor_pengirim>" \
  --message "<pesan asli approver>"
```

Script ini:
1. POST ke Laravel `/api/approval/decision`
2. Laravel validasi (approver berwenang? level giliran? sudah diproses?)
3. Laravel update DB dalam transaksi + trigger notif level berikutnya
4. Script kirim `reply` dari response ke approver via WA bridge

Format yang diterima approver:

| Command | Status sebelum | Hasil |
|---------|---------------|-------|
| `/approve <ID>` | pending | approved, progress maju, notif level berikutnya |
| `/tolak <ID> <alasan>` | pending | rejected, sibling rows void (chain berhenti) |
| `/batal <ID> <alasan>` | approved | rejected, **saldo cuti dikembalikan** |

---

## Bagian D — Skema Data (referensi)

Satu pengajuan cuti = **N row** di `leave_requests`, satu per approver.

| Kolom | Arti |
|-------|------|
| `token` | pengenal pengajuan (sama untuk semua row satu pengajuan) |
| `approval_id` | NPK approver yang bertanggung jawab pada row ini |
| `approval_level` | level approver ini dalam chain (1, 2, 3, ...) |
| `approval_progress` | pointer level aktif (di-update saat level approve) |
| `status` | pending / approved / rejected |
| `void` | string `'false'` (bukan NULL) |

Aturan sequential: level N hanya dikabari jika semua level 1..N-1 `approved`.
Rejected menghentikan chain — level di atasnya tidak dikabari.

---

## Troubleshooting

| Gejala | Cek |
|--------|-----|
| Webhook 401 | Secret tidak sama antara `hermes config`/`webhook subscribe` dan `APPROVAL_WEBHOOK_SECRET` |
| Webhook 200 tapi `{"status":"ignored","reason":"script"}` | Normal — script selesai tanpa perlu kirim (tidak ada yang eligible), tidak memicu LLM |
| Laravel 401 saat dipanggil script | `APPROVAL_API_TOKEN` beda antara `~/.hermes/approval-wa.env` dan `.env` Laravel |
| Script timeout di webhook | Laravel lambat (DB tidak terjangkau) — cek `DB_CII_LOGIN_TIMEOUT` |
| Tidak ada WA terkirim | `curl http://127.0.0.1:3000/health` → harus `{"status":"connected"}` |
| WA session logged out | Bridge perlu re-auth (scan QR), cek `~/.hermes/whatsapp/bridge.log` |
| Notif dobel | Cron sweep + webhook trigger sama-sama jalan → normal, cron hanya safety net |