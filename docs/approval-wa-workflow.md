# Approval Cuti via WhatsApp — Dokumentasi

> **Untuk setup di server Hermes lain → [`hermes/HERMES_SETUP.md`](hermes/HERMES_SETUP.md)**

Dokumen ini menjelaskan logika bisnis. Setup/install ada di `hermes/HERMES_SETUP.md`.

---

## Ringkasan

Sistem approval cuti via WhatsApp 2 arah:

- **Outbound** — Hermes cron (tiap 5 jam) cek `leave_requests` berstatus `pending`,
  kirim notif WhatsApp ke approver yang gilirannya
- **Inbound** — approver balas `/approve <ID>` atau `/tolak <ID> <alasan>`,
  Hermes proses, update DB, balas konfirmasi

---

## Skema DB

Satu pengajuan cuti menghasilkan **N row** di `leave_requests` — satu per approver
dalam chain. Semua row satu pengajuan punya `token` yang sama.

| Kolom | Isi | Arti |
|-------|-----|------|
| `id` | auto | Primary key row ini |
| `token` | string random | Pengenal **pengajuan** (bukan row) |
| `NPK` | NPK pemohon | Yang mengajukan |
| `approval_id` | NPK approver | **Approver bertanggung jawab untuk row ini** |
| `approval_level` | int | Level approver dalam chain (1, 2, 3, ...) |
| `approval_progress` | int | Selalu `1` saat dibuat |
| `status` | pending/approved/rejected | Status keputusan row ini |
| `void` | `'false'` | Flag void (string, bukan NULL) |

Pendukung: `approval_rules` (`approval_id` = NPK approver, `level`, `rules_id`),
`PKWT` (`NPK`, `NAMA`, `HP` — sumber nomor WhatsApp).

---

## Aturan Sequential (Inti)

```
Row level 1 pending  → notif ke NPK level 1
Row level 2 pending  → notif ke NPK level 2 HANYA jika row level 1 = approved
Row level 3 pending  → notif ke NPK level 3 HANYA jika row level 1 & 2 = approved
```

**Rejected menghentikan chain.** Jika row level N di-reject, row level > N di chain
yang sama tidak akan menerima notifikasi — walau statusnya masih `pending` di DB.

Implementasi: `LeaveApprovalQuery::getPendingForNotification()`
- group row pending by `token`
- cek `statusMap[token][1..N-1] === 'approved'` untuk semua level sebelumnya
- skip chain jika ada level yang `rejected`

> **Perbaikan bug (commit `a2f7c14`):** sebelumnya notifikasi dikirim ke semua
> approver dalam chain sekaligus. Sekarang satu-per-sesuai level.

---

## Komponen Kode

| File | Fungsi |
|------|--------|
| `app/Services/ApprovalWa/LeaveApprovalNotifier.php` | **Notifikasi per-token** — pilih level yang giliran, format, kirim |
| `app/Services/ApprovalWa/LeaveApprovalEffects.php` | Efek approve/reject: advance progress, finalisasi (potong saldo + overtime), void sibling rows |
| `app/Services/ApprovalWa/LeaveApprovalQuery.php` | Query pending, filter sequential, lookup approver, validasi, update DB |
| `app/Services/ApprovalWa/PhoneFormatter.php` | Normalisasi & validasi nomor HP (`08xxx`/`+62xxx`/`8xxx` → `62xxx`) |
| `app/Services/ApprovalWa/WaMessageFormatter.php` | Format pesan outbound (dengan deep link) + konfirmasi + error |
| `app/Services/ApprovalWa/WaGateway.php` | POST ke WA bridge (`/send`, payload `chatId` JID) |
| `app/Services/ApprovalWa/InboundApprovalProcessor.php` | Parse `/approve`/`/tolak`, validasi, update DB, cascade notify |
| `app/Console/Commands/CheckPendingLeaveApproval.php` | `approval:check-pending [--dry-run]` — sweep semua pending |
| `app/Console/Commands/NotifyLeaveApprovalToken.php` | `approval:notify-token <token> [--dry-run]` — notif 1 pengajuan |
| `app/Http/Controllers/Api/ApprovalWebhookController.php` | `POST /api/approval/webhook` (opsional, kalau gateway pakai webhook) |
| `app/Console/Kernel.php` | Schedule Laravel: `approval:check-pending` tiap 5 jam (safety net) |

### Trigger Notifikasi

| Event | Hook | Efek |
|-------|------|------|
| Karyawan submit pengajuan | `PengajuanCutiController::store()` | Notif level 1 **langsung** |
| Approve via web portal | `LeaveApprovalController::approve()` | Notif level N+1 **langsung** |
| Approve via WhatsApp | `InboundApprovalProcessor::handle()` | Notif level N+1 **langsung** |
| Cron tiap 5 jam | `approval:check-pending` | Sweep semua pending, group by token, safety net |

Semua notifikasi per-token melewati `LeaveApprovalNotifier::pickEligibleLevel()` —
hanya level yang "giliran" yang dikabari.

### Artefak Hermes

| File | Target |
|------|--------|
| `docs/hermes/skill/approval-manager.SKILL.md` | `~/.hermes/skills/approval-manager/SKILL.md` |
| `docs/hermes/scripts/check-leave-approval.sh` | `~/.hermes/scripts/check-leave-approval.sh` |

---

## Command

```bash
# Sweep semua pending (cron / safety net)
php8.3 artisan approval:check-pending --dry-run
php8.3 artisan approval:check-pending

# Notifikasi 1 pengajuan (by token)
php8.3 artisan approval:notify-token <token> --dry-run
php8.3 artisan approval:notify-token <token>
```

> ⚠️ Tanpa `--dry-run` **langsung mengirim**. Selalu dry-run dulu saat verifikasi.

---

## Format Pesan

### Outbound

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

### Inbound — Diterima

```
/approve 849          /approve #849          APPROVE 849
/tolak 849 alasan     /tolak 849 alasan     REJECT 849 alasan
```

### Konfirmasi

```
✔️ *Konfirmasi Keputusan*

Pengajuan cuti *#859* telah *✅ DISETUJUI*.
Oleh: RAKHMAT BUDIYONO
Waktu: 10/11/2026 15:30 WIB

Terima kasih.
```

---

## Keamanan

1. **Otorisasi 2 lapis** — nomor HP → `PKWT.HP` → cek NPK ada di `approval_rules.approval_id`
2. **Sequential enforcement** — approver hanya bisa memutuskan row dengan `approval_id` = NPK-nya
3. **Anti salah-ID** — match persis, tidak ada fallback ke request lain
4. **Nomor tidak terdaftar** → ditolak
5. `WA_WEBHOOK_SECRET` untuk autentikasi endpoint webhook

---

## Troubleshooting

| Gejala | Cek |
|--------|-----|
| "No pending requests" padahal ada | Filter sequential — cek status row level sebelumnya |
| "Failed to send" | `WA_GATEWAY_URL` benar? `curl http://localhost:3000/health` |
| "invalid/missing phone" | `PKWT.HP` kosong untuk NPK itu |
| Approver unauthorized | `PKWT.HP` format salah, atau NPK tidak ada di `approval_rules` |
| SSPI error | SQL Server masih Windows Auth — ubah mixed mode + buat SQL login |
| Timeout query | `PKWT` besar tanpa index — query sudah dipecah di `LeaveApprovalQuery` |
