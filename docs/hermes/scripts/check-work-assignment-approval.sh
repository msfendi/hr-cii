#!/bin/bash
# Hermes cron script: sweep approval IZIN DINAS (Work Assignment) dan kirim
# notif WA ke approver yang sedang giliran.
#
# Dokumen: Surat Ijin Dinas Luar (tabel work_assignment_requests).
# Jalur: approval-outbound.py --doc izin-dinas → payload disiapkan Laravel,
#        pesan dikirim oleh messaging layer (bukan Laravel).
#
# Deploy: cp docs/hermes/scripts/check-work-assignment-approval.sh ~/.hermes/scripts/
#         chmod +x ~/.hermes/scripts/check-work-assignment-approval.sh
#
# Catatan:
# - Script ini LANGSUNG mengirim WhatsApp (bukan dry-run).
# - Aman diulang: rantai approval Izin Dinas tersimpan sebagai JSON, dan
#   Laravel hanya menyiapkan level yang benar-benar sedang giliran.
# - JANGAN menjadwalkan ini bersamaan dengan `php artisan approval:notify-izin-dinas`
#   (jalur Laravel langsung) — approver bisa menerima pesan yang sama dua kali.
# - Jangan pula memakai `approval-outbound.py` TANPA --doc untuk job lain:
#   tanpa --doc script menyapu cuti + izin dinas sekaligus, sehingga dokumen
#   ini bisa tersapu dua kali kalau ada job lain juga berjalan.

set -uo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PY_BIN="${PY_BIN:-python3}"

exec "$PY_BIN" "$SCRIPT_DIR/approval-outbound.py" --doc izin-dinas