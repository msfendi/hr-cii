#!/bin/bash
# Hermes cron script: cek pending leave approval dan kirim notif WA
#
# Deploy: cp docs/hermes/scripts/check-leave-approval.sh ~/.hermes/scripts/
#         chmod +x ~/.hermes/scripts/check-leave-approval.sh
#         lalu edit HR_CII_PATH di bawah sesuai lokasi repo di server ini.
#
# Catatan: command ini LANGSUNG mengirim pesan WhatsApp (bukan dry-run).
#         Untuk preview: php8.3 artisan approval:check-pending --dry-run

set -uo pipefail

HR_CII_PATH="${HR_CII_PATH:-/home/hermes/workspace/hr-cii}"
PHP_BIN="${PHP_BIN:-php8.3}"

if [ ! -d "$HR_CII_PATH" ]; then
    echo "[approval-wa] ERROR: HR_CII_PATH not found: $HR_CII_PATH"
    exit 1
fi

cd "$HR_CII_PATH" || exit 1

exec "$PHP_BIN" artisan approval:check-pending 2>&1
