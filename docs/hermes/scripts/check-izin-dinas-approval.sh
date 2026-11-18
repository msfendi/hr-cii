#!/bin/bash
# Hermes cron script: cek pending approval IZIN DINAS dan kirim notif WA.
#
# Dokumen: Surat Ijin Dinas Luar (tabel work_assignment_requests).
# Beda dari cuti: rantai approval disimpan sebagai JSON (approval_chain +
# answers), dan keputusan via WA memakai command /approve-izin-dinas<ID>.
#
# Deploy: cp docs/hermes/scripts/check-izin-dinas-approval.sh ~/.hermes/scripts/
#         chmod +x ~/.hermes/scripts/check-izin-dinas-approval.sh
#         lalu sesuaikan HR_CII_PATH bila repo tidak di lokasi default.
#
# Catatan: command ini LANGSUNG mengirim pesan WhatsApp (bukan dry-run).
#         Untuk preview: php8.3 artisan approval:notify-izin-dinas --dry-run
#
# Aman dijalankan berulang: hanya level yang sedang giliran yang dikirimi.

set -uo pipefail

HR_CII_PATH="${HR_CII_PATH:-/home/hermes/workspace/hr-cii}"
PHP_BIN="${PHP_BIN:-php8.3}"

if [ ! -d "$HR_CII_PATH" ]; then
    echo "[approval-wa/izin-dinas] ERROR: HR_CII_PATH not found: $HR_CII_PATH"
    exit 1
fi

cd "$HR_CII_PATH" || exit 1

exec "$PHP_BIN" artisan approval:notify-izin-dinas 2>&1
