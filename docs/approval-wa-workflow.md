# Approval Cuti via WhatsApp

> **Setup/deploy → [`hermes/HERMES_SETUP.md`](hermes/HERMES_SETUP.md)**

## Arsitektur (Opsi C — decoupling)

**Laravel = business logic. Hermes = messaging layer.**

- Laravel: validasi approver, filter sequential, update status, potong saldo,
  finalisasi overtime. **Tidak pernah mengirim WhatsApp sendiri.**
- Hermes: kirim pesan, terima pesan, teruskan keputusan.
  **Tidak pernah query DB dan tidak memutuskan validitas approval.**

```
Laravel                                Hermes
  submit / approve ──POST webhook──────► approval-webhook.py
                        ──GET trigger──► (ambil payload)
                        ────────────────► WA bridge → approver
  GET /approval/pending ◄────cron─────── approval-outbound.py
  POST /approval/decision ◄───inbound── approval-inbound.py
```

## Skema DB

Satu pengajuan = **N row** di `leave_requests` (satu per approver), semua row
share `token` yang sama.

| Kolom | Arti |
|-------|------|
| `token` | pengenal pengajuan |
| `approval_id` | NPK approver untuk row ini |
| `approval_level` | level approver dalam chain |
| `approval_progress` | pointer level aktif |
| `status` | pending / approved / rejected |
| `void` | `'false'` (string) |

**Sequential:** level N hanya dikabari jika level 1..N-1 sudah `approved`.
**Rejected menghentikan chain** — level di atasnya tidak dikabari.

## Komponen Kode (Laravel)

| File | Fungsi |
|------|--------|
| `app/Services/ApprovalWa/LeaveApprovalQuery.php` | query pending, filter sequential, conditional UPDATE, validasi approver |
| `app/Services/ApprovalWa/LeaveApprovalEffects.php` | advance progress, finalisasi (saldo + overtime), void siblings, reverse |
| `app/Services/ApprovalWa/LeaveApprovalNotifier.php` | pilih level eligible, format pesan, kirim (mode single-server) |
| `app/Services/ApprovalWa/ApprovalWebhookTrigger.php` | **trigger Hermes via webhook** (HMAC V2) |
| `app/Services/ApprovalWa/ApprovalApiPayload.php` | payload JSON untuk messaging layer |
| `app/Services/ApprovalWa/PhoneFormatter.php` | normalisasi HP (`08xxx`/`+62xxx` → `62xxx`) |
| `app/Services/ApprovalWa/WaMessageFormatter.php` | format pesan + deep link |
| `app/Services/ApprovalWa/WaGateway.php` | kirim ke bridge (fallback mode) |
| `app/Services/ApprovalWa/WaSendThrottle.php` | jitter + rate limit (fallback mode) |
| `app/Services/ApprovalWa/InboundApprovalProcessor.php` | parse, validasi, transaksi, cascade |
| `app/Http/Controllers/Api/ApprovalApiController.php` | `pending` / `trigger` / `decision` |
| `app/Http/Middleware/AuthenticateApprovalApi.php` | bearer token + IP allowlist |
| `app/Console/Commands/CheckPendingLeaveApproval.php` | `approval:check-pending` (sweep) |
| `app/Console/Commands/NotifyLeaveApprovalToken.php` | `approval:notify-token <token>` |

## Trigger Notifikasi

| Event | Hook | Efek |
|-------|------|------|
| Submit pengajuan | `PengajuanCutiController::store()` | trigger Hermes → notif level 1 |
| Approve web portal | `LeaveApprovalController::approve()` | trigger Hermes → notif level N+1 |
| Approve via WA | `InboundApprovalProcessor::handle()` | trigger Hermes → notif level N+1 |
| Cron 5 jam | `approval:check-pending` / `approval-outbound.py` | safety net |

Semua setelah `DB::commit()` — tidak ada notif untuk data yang gagal disimpan.

## Command

```bash
# Sweep semua pending (safety net)
php8.3 artisan approval:check-pending --dry-run
php8.3 artisan approval:check-pending

# Notifikasi 1 pengajuan
php8.3 artisan approval:notify-token <token> --dry-run
php8.3 artisan approval:notify-token <token>
```

> ⚠️ Tanpa `--dry-run` **langsung mengirim**.

## Inbound — Diterima

```
/approve 849        APPROVE 849        /approve #849
/tolak 849 alasan   REJECT 849 alasan
/batal 849 alasan   BATAL 849 alasan
```

| Command | Status sebelum | Hasil |
|---------|---------------|-------|
| `/approve <ID>` | pending | approved, progress maju, notif level berikutnya |
| `/tolak <ID> <alasan>` | pending | rejected, sibling rows void |
| `/batal <ID> <alasan>` | approved | rejected, **saldo dikembalikan** |

`/batal` ditolak kalau level berikutnya sudah bertindak.

## Keamanan & Konsistensi

1. **Otorisasi 2 lapis** — HP → `PKWT.HP` → NPK ada di `approval_rules.approval_id`
2. **Anti salah-ID** — NPK approver harus match `leave_requests.approval_id` row itu
3. **Sequential enforcement** — tidak bisa skip level
4. **Atomic** — conditional UPDATE (`WHERE status='pending'`) + transaction,
   mencegah dobel potong saldo saat double-tap/retry
5. **API auth** — bearer token + optional IP allowlist (fail-closed kalau token kosong)
6. **Webhook auth** — HMAC-SHA256 V2 (`X-Webhook-Timestamp` + `X-Webhook-Signature-V2`)
7. **Anti-ban** — jitter 15s + gap 15s + max 6 pesan/menit

## Troubleshooting

| Gejala | Cek |
|--------|-----|
| "No pending" padahal ada | Filter sequential — cek status level sebelumnya |
| Failed to send | `curl http://127.0.0.1:3000/health` |
| invalid/missing phone | `PKWT.HP` kosong/format salah |
| SSPI error | SQL Server masih Windows Auth — pindah ke mixed mode |
| Webhook 401 | Secret beda antara Laravel dan Hermes |
| Laravel 401 | `APPROVAL_API_TOKEN` beda |