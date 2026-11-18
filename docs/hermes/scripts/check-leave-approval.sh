#!/bin/bash
# Hermes cron script: sweep approval CUTI dan kirim notif WA ke approver.
#
# Dokumen: pengajuan cuti (tabel leave_requests).
# Jalur: approval-outbound.py --doc cuti  → payload disiapkan Laravel,
#        pesan dikirim oleh messaging layer (bukan Laravel).
#
# Deploy: cp docs/hermes/scripts/check-leave-approval.sh ~/.hermes/scripts/
#         chmod +x ~/.hermes/scripts/check-leave-approval.sh
#
# Catatan:
# - Script ini LANGSUNG mengirim WhatsApp (bukan dry-run).
# - Aman diulang: Laravel hanya menyiapkan payload untuk level yang sedang
#   giliran, jadi approver yang sudah lewat tidak dikirimi lagi.
# - JANGAN menjadwalkan ini bersamaan dengan `php artisan approval:check-pending`
#   (jalur Laravel langsung) — approver bisa menerima pesan yang sama dua kali.

set -uo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PY_BIN="${PY_BIN:-python3}"

exec "$PY_BIN" "$SCRIPT_DIR/approval-outbound.py" --doc cuti