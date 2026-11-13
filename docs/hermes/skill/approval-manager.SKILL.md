---
name: approval-manager
description: Use when managing leave approval via WhatsApp gateway.
---

# Skill: approval-manager

2-way WhatsApp approval workflow for `leave_requests` in the CII HR system.

**Architecture (Opsi C — decoupling):** Laravel holds all business logic.
Hermes is the messaging layer. The agent never queries the DB directly and
never decides approval validity — it only relays messages.

```
Laravel (business logic)          Hermes (messaging layer)
┌────────────────────────┐         ┌──────────────────────────┐
│ submit pengajuan       │         │ cron 5 jam (safety net)   │
│   → webhook trigger ───┼──POST──►│   → GET /pending → send   │
│ approve level N        │         │                          │
│   → webhook trigger ───┼──POST──►│ inbound WA (approver)     │
│ /api/approval/pending  │◄─GET────│   → POST /decision        │
│ /api/approval/trigger  │◄─POST───│   → send reply            │
│ /api/approval/decision │◄─POST───│                          │
└────────────────────────┘         └──────────────────────────┘
```

## Paths & Env

```bash
export HR_CII_API_URL="http://192.168.0.68:8010"   # Laravel API base
export APPROVAL_API_TOKEN="<bearer token>"          # same as Laravel APPROVAL_API_TOKEN
export WA_BRIDGE_URL="http://127.0.0.1:3000"        # Hermes Baileys bridge
```

## Trigger Pattern (Inbound WA)

Load this skill when a WhatsApp message matches:
- `/approve <ID>` or `APPROVE <ID>` (with or without `#`)
- `/tolak <ID> <alasan>` or `REJECT <ID> <alasan>`
- `/batal <ID> <alasan>` or `BATAL <ID> <alasan>` — cancels an already-approved request

**Never validate approver authorization yourself** — Laravel does that. The agent
parses, relays, and replies.

### Inbound Flow

```bash
python3 ~/.hermes/scripts/approval-inbound.py \
  --phone "<nomor_pengirim>" \
  --message "<pesan asli dari approver>"
```

The script POSTs to `/api/approval/decision` and sends the returned `reply`
back to the approver via the WA bridge.

Dry-run (no WA reply sent):
```bash
python3 ~/.hermes/scripts/approval-inbound.py \
  --phone "<nomor_pengirim>" --message "<pesan>" --dry-run
```

### Decision Semantics (decided by Laravel)

| Command | Status before | Result |
|---------|---------------|--------|
| `/approve <ID>` | `pending` | → `approved`, advance progress, notify next level |
| `/tolak <ID> <alasan>` | `pending` | → `rejected`, void sibling rows (chain stops) |
| `/batal <ID> <alasan>` | `approved` | → `rejected`, **restore leave balance**, un-void siblings |

Laravel returns HTTP 200 with `status: ok|rejected|ignored` and a `reply` string.
Always send `reply` to the approver — including error replies.

### Atomicity (Laravel side)

Approve/reject runs inside `DB::connection('cii')->beginTransaction()`. Status
write uses conditional UPDATE (`WHERE id = ? AND status = 'pending'`) so two
concurrent approvals cannot both pass — the second gets 0 affected rows and
aborts **before** any balance change.

## Outbound

### Via webhook (instant — triggered by Laravel)

Laravel POSTs to the Hermes webhook route when a leave is submitted or a level
is approved. The webhook runs `approval-webhook.py`, which fetches the payload
from `/api/approval/trigger` and sends the WhatsApp message.

**The message text is rendered by Laravel** (`WaMessageFormatter`) and delivered
in the payload's `message` field — the messaging layer sends it verbatim. It
contains: nama pemohon, NPK, jenis cuti, tanggal pengajuan, periode, durasi,
alasan, no. pengajuan, posisi level approval, plus deep links for
`/approve` and `/tolak`. Do not re-format it.

**Saldo cuti:** untuk **Cuti Tahunan** saja, pesan juga memuat blok saldo —
sisa saldo tahun berjalan (tahun tanggal mulai) dan sisa setelah pengajuan
disetujui. Kalau saldo tidak cukup, kekurangannya ditandai **HUTANG CUTI**.
Jenis cuti lain (menikah, melahirkan, pribadi, dll.) tidak menyertakan blok ini
karena tidak memakai saldo. Saldo dibaca dari `leave_balances`
(NPK + leave_type_id + year).

### Via cron (safety net — sweeps all pending)

```bash
python3 ~/.hermes/scripts/approval-outbound.py --dry-run   # preview, NO send
python3 ~/.hermes/scripts/approval-outbound.py              # live send
python3 ~/.hermes/scripts/approval-outbound.py --token <token>  # one pengajuan
```

## Phone Number Handling

Nomor telepon approver diambil dari `PKWT.HP`. **Tidak semua nomor Indonesia.**

Normalization (harus sinkron antara Laravel & script Python):
`app/Services/ApprovalWa/PhoneFormatter.php` ↔ `docs/hermes/scripts/approval_phone.py`

| Input | Output | Meaning |
|-------|--------|---------|
| `+60127383232` | `60127383232` | Explicit international — country code kept |
| `+84913662215` | `84913662215` | Vietnam |
| `+94772605624` | `94772605624` | Sri Lanka |
| `081390207272` | `6281390207272` | Indonesia local |
| `84913662215` | `84913662215` | Non-Indonesian, no `+` → kept as-is |
| `60127383232@s.whatsapp.net` | `60127383232` | Baileys JID |

Rules:
1. `+` prefix = already international → **country code preserved verbatim**.
2. JID form → digits only.
3. No `+` → Indonesian mobile pattern checked first (operator prefix
   table `ID_MOBILE_PREFIXES`), then other country codes.
4. Ambiguous → **rejected**, never force-prefixed with 62. A wrong number
   reaching the wrong person is worse than a message not sent.

Output is always digits-only so it can be suffixed with `@s.whatsapp.net`
to form a Baileys chat id. **Never send a `+` to the bridge** — it needs
raw digits.

## Chain Sync (Manage Approval berubah)

Saat orang HR menambah / menghapus / memindah level di **Manage Approval**,
pengajuan yang masih `pending` di `leave_requests` ikut disinkronkan —
approver yang dihapus dibatalkan, approver baru ditambahkan, dan level
dirapikan `1..N`.

Pengajuan yang **sudah diputuskan** (`approved`/`rejected`) tidak pernah
diubah.

Kalau pengajuan pending tampak "macet" (approver berikutnya tidak pernah
dapat notifikasi) setelah master approval diubah:

```bash
php artisan approval:sync-chains --dry-run    # lihat group yang perlu dirapikan
php artisan approval:sync-chains              # rapikan + sinkronkan
php artisan approval:sync-chains --group=12   # satu group saja
```

Penyebab umum: level di master berlubang (`1,3,4`) sehingga cek "semua level
sebelumnya sudah approved" tidak pernah terpenuhi untuk level 3+.

## Throttle (anti-ban)

Applied in the messaging layer scripts, not in Laravel:
- jitter: random 70-130% of `WA_JITTER_SECONDS` (default 15s) before each send
- min gap: `WA_MIN_GAP_SECONDS` (default 15s) between messages
- burst guard: max `WA_MAX_PER_MINUTE` (default 6) per minute

Laravel's own `WaSendThrottle` applies only in single-server fallback mode.

## Key Tables (Laravel side — for reference only)

| Table | Key Columns |
|-------|-------------|
| `leave_requests` | `id`, `token`, `NPK`, `approval_id`, `approval_level`, `approval_progress`, `status`, `void` |
| `approval_rules` | `rules_id`, `approval_id` (NPK approver), `level`, `name` |
| `PKWT` | `NPK`, `NAMA`, `HP` (phone source) |

One leave submission creates **N rows** in `leave_requests` — one per approver
in the chain. All rows share the same `token`. `approval_id` = NPK of the
approver responsible for that row.

## API Endpoints (Laravel)

All under `/api/approval/*`, auth = `Authorization: Bearer $APPROVAL_API_TOKEN`.

| Method | Path | Purpose |
|--------|------|---------|
| GET | `/api/approval/pending` | list pengajuan eligible + pesan siap kirim |
| POST | `/api/approval/trigger` | payload 1 pengajuan by token (`{token}`) |
| POST | `/api/approval/decision` | proses keputusan (`{sender, message}`) |

## Scripts Location

```
~/.hermes/scripts/approval-outbound.py   # cron sweep + per-token notify
~/.hermes/scripts/approval-inbound.py    # relay inbound decision + reply
~/.hermes/scripts/approval-webhook.py    # webhook handler (Laravel → Hermes)
```

Source of truth: `docs/hermes/scripts/` in the `hr-cii` repo.

## Troubleshooting

| Symptom | Check |
|---------|-------|
| No message sent | `HR_CII_API_URL` + `APPROVAL_API_TOKEN` set? Laravel reachable? |
| "not eligible" | Previous level not approved yet — expected behavior |
| Approval rejected | Read `reply` from Laravel; it explains why (unauthorized / wrong level / already processed) |
| Bridge error | `curl http://127.0.0.1:3000/health` → must show `{"status":"connected"}` |
| WA session logged out | Bridge needs re-auth (QR scan); check `~/.hermes/whatsapp/bridge.log` |
| Laravel 401 | Token mismatch between Hermes env and Laravel `APPROVAL_API_TOKEN` |
