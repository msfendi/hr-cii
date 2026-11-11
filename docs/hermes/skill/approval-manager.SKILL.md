---
name: approval-manager
description: Use when managing leave approval via WhatsApp gateway.
---

# Skill: approval-manager

2-way WhatsApp approval workflow for `leave_requests` in the CII HR system.

## Paths

Set these once per server before use:

```bash
export HR_CII_PATH="/path/to/hr-cii"     # Laravel repo
export WA_BOT_NUMBER="6287731665679"    # bot WhatsApp number
```

## Trigger Pattern (Inbound WA)

Load this skill automatically when a WhatsApp message matches:
- `/approve <ID>` or `APPROVE <ID>` (with or without `#`)
- `/tolak <ID> <alasan>` or `REJECT <ID> <alasan>`

When triggered inbound:
1. Parse decision + ID + reason from message
2. Normalize sender phone → lookup NPK via `PKWT.HP`
3. Validate approver authorized (NPK in `approval_rules.approval_id`) AND matches
   `leave_requests.approval_id` for that exact row
4. Validate sequential: all previous levels in the chain already `approved`
5. Update DB status to `approved` / `rejected`
6. Send confirmation reply back to WhatsApp

## Outbound (cron)

```bash
cd "$HR_CII_PATH"
php8.3 artisan approval:check-pending --dry-run   # preview, NO send
php8.3 artisan approval:check-pending              # live send
```

⚠️ Without `--dry-run` messages ARE sent. Always dry-run first when verifying.

## Per-Request Notification (primary flow)

Notifications are sent **per pengajuan (token)**, triggered by the app itself —
not only by cron.

| Trigger | Effect |
|---------|--------|
| Employee submits leave form | Level 1 approver notified immediately |
| Level N approved (web portal) | Level N+1 notified immediately |
| Level N approved (WhatsApp) | Level N+1 notified immediately |
| Cron (every 5h) | Safety net — sweep all pending, group by token, notify eligible levels |

```bash
# Manual notification for one pengajuan (testing / re-send)
php8.3 artisan approval:notify-token <token> --dry-run
php8.3 artisan approval:notify-token <token>
```

Get a token:
```sql
SELECT TOP 5 token, id, NPK, approval_level, status FROM leave_requests
WHERE status = 'pending' ORDER BY id DESC
```

## Decision Effects (approve/reject)

Approving via WhatsApp applies the SAME side effects as the web portal:
- advances `approval_progress` to the next level, or
- finalizes at the last level (deducts leave balance + creates Overtime CT/CU)

Rejecting voids all sibling rows in the same token (chain stops).

Implemented in `App\Services\ApprovalWa\LeaveApprovalEffects` — shared by both paths.

## Inbound Processing

```bash
cd "$HR_CII_PATH"

SENDER_PHONE="6281234567890"   # nomor pengirim (raw dari WA)
MESSAGE="/approve 849"         # pesan dari approver

php8.3 artisan tinker --execute="
  use App\\Services\\ApprovalWa\\InboundApprovalProcessor;
  \$reply = InboundApprovalProcessor::handle('$SENDER_PHONE', '$MESSAGE');
  echo \$reply;
"
```

The command echoes the reply text. Send it back via WA bridge:

```bash
curl -X POST http://localhost:3000/send \
  -H "Content-Type: application/json" \
  -d '{"chatId":"<62xxx>@s.whatsapp.net","message":"<reply>"}'
```

Or use `App\Services\ApprovalWa\WaGateway::send($phone, $reply)`.

## Context

- **App**: Laravel, path in `$HR_CII_PATH`
- **DB**: SQL Server `192.168.3.55:13279`, database `CII`, user `hr_app`
- **PHP**: `php8.3`
- **WA Bridge**: Hermes WhatsApp gateway, default `http://localhost:3000`
- **Bot number**: `$WA_BOT_NUMBER`

## Key Tables

| Table | Key Columns |
|-------|-------------|
| `leave_requests` | `id`, `token`, `NPK`, `approval_id`, `approval_level`, `approval_progress`, `status`, `void` |
| `approval_rules` | `rules_id`, `approval_id` (NPK approver), `level`, `name` |
| `PKWT` | `NPK`, `NAMA`, `HP` (phone source) |

## Schema Facts (CRITICAL)

One leave submission creates **N rows** in `leave_requests` — one per approver in
the chain. All rows of one submission share the same `token`.

- `approval_id` = **NPK of the approver responsible for that row** (NOT a group ID)
- `approval_level` = that approver's level in the chain (1, 2, 3, ...)
- `approval_progress` = always `1` when created
- `status` = `pending` | `approved` | `rejected`
- `void` = string `'false'` (not NULL)

## Sequential Rule

```
level 1 pending → notify NPK level 1
level 2 pending → notify NPK level 2 ONLY IF level 1 row = approved
level 3 pending → notify NPK level 3 ONLY IF level 1 AND level 2 rows = approved
```

A `rejected` row stops the whole chain — higher levels never get notified,
even though their rows remain `pending` in the DB.

Implementation: `LeaveApprovalQuery::getPendingForNotification()` groups pending
rows by `token` and checks every previous level's status.

## Phone Normalization

`App\Services\ApprovalWa\PhoneFormatter::normalize()`

| Input | Output |
|-------|--------|
| `081234567890` | `6281234567890` |
| `+6281234567890` | `6281234567890` |
| `81234567890` | `6281234567890` |
| `0812-3456-7890` | `6281234567890` |
| unrecognized | `null` → skipped + logged |

Validation: `/^628[0-9]{8,12}$/`

## Service Classes

```
app/Services/ApprovalWa/
├── PhoneFormatter.php
├── LeaveApprovalQuery.php
├── LeaveApprovalNotifier.php   ← per-token notify + level selection
├── LeaveApprovalEffects.php    ← advance progress / finalize / void siblings
├── WaMessageFormatter.php
├── WaGateway.php
└── InboundApprovalProcessor.php

app/Console/Commands/
├── CheckPendingLeaveApproval.php   (approval:check-pending)
└── NotifyLeaveApprovalToken.php    (approval:notify-token <token>)

app/Http/Controllers/Api/
└── ApprovalWebhookController.php   (POST /api/approval/webhook)
```

## Inbound Webhook (optional)

Endpoint `POST /api/approval/webhook`:

```json
{
  "sender": "6281234567890",
  "message": "/approve 123"
}
```

Set `WA_WEBHOOK_SECRET` → gateway must send header `X-WA-Token` with that value.

## Env Variables

```env
DB_CII_CONNECTION=cii
DB_CII_HOST=192.168.3.55
DB_CII_PORT=13279
DB_CII_DATABASE=CII
DB_CII_USERNAME=hr_app
DB_CII_PASSWORD=<password>

WA_GATEWAY_URL=http://localhost:3000
WA_GATEWAY_TOKEN=
WA_WEBHOOK_SECRET=
WA_BOT_NUMBER=6287731665679
```

## Security Rules

- Only NPKs in `approval_rules.approval_id` can send decisions
- Approver can only decide rows where `leave_requests.approval_id` matches their NPK
- All previous levels must be `approved` before the next approver can act
- Phone lookup against `PKWT.HP`, normalized to 62xxx
- Sender not found as approver → rejected with WA message
- Never decide on a different request ID than the one notified

## Troubleshooting

| Symptom | Check |
|---------|-------|
| "No pending requests" but rows exist | Sequential filter — check previous level status in same token |
| "Failed to send" | `WA_GATEWAY_URL`; bridge health `curl http://localhost:3000/health` |
| "invalid/missing phone" | `PKWT.HP` empty for that NPK |
| Approver unauthorized | `PKWT.HP` format wrong, or NPK missing from `approval_rules` |
| SSPI error | SQL Server still Windows-Auth only — switch to mixed mode + SQL login |
| Query timeout | `PKWT` is large/unindexed — queries are split in `LeaveApprovalQuery` |
| Cron not firing | `hermes cron list`; check `hermes cron edit <job_id>` |
