#!/bin/bash
# Pastikan validasi server masih menolak submit tidak lengkap (tidak membuat row).
set -u
BASE="http://127.0.0.1:8010"
JAR=/tmp/wa_live_jar.txt

BEFORE=$(cd /home/hermes/workspace/hr-cii && php8.3 artisan tinker --execute="echo App\Models\WorkAssignmentRequest::count();" 2>/dev/null | tail -1)

TOKEN=$(grep -oE 'name="_token" value="[^"]+"' /tmp/wa_form.html | head -1 | sed 's/.*value="//;s/"//')

echo "1. submit TANPA tanggal & field wajib"
curl -s -b "$JAR" -c "$JAR" -o /tmp/wa_neg.html -w "   HTTP %{http_code} -> %{redirect_url}\n" -m 90 \
  -X POST "$BASE/work-assignment/submit" \
  --data-urlencode "_token=$TOKEN" \
  --data-urlencode "dinas_ke=" \
  --data-urlencode "keperluan=" \
  --data-urlencode "jam_keluar=" \
  --data-urlencode "rencana_kembali="

echo "2. submit dengan 1 tanggal tapi rencana kembali < jam keluar"
curl -s -b "$JAR" -c "$JAR" -o /tmp/wa_neg2.html -w "   HTTP %{http_code} -> %{redirect_url}\n" -m 90 \
  -X POST "$BASE/work-assignment/submit" \
  --data-urlencode "_token=$TOKEN" \
  --data-urlencode "duty_dates[]=2026-12-07" \
  --data-urlencode "dinas_ke=Kantor Uji" \
  --data-urlencode "keperluan=Uji validasi" \
  --data-urlencode "jam_keluar=17:00" \
  --data-urlencode "rencana_kembali=08:00"

AFTER=$(cd /home/hermes/workspace/hr-cii && php8.3 artisan tinker --execute="echo App\Models\WorkAssignmentRequest::count();" 2>/dev/null | tail -1)

echo
echo "rows sebelum: $BEFORE  ->  sesudah: $AFTER"
if [ "$BEFORE" = "$AFTER" ]; then
  echo "OK   tidak ada row dibuat (validasi bekerja)"
else
  echo "FAIL ada row terbuat dari submit tidak valid"
fi