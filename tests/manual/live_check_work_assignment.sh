#!/bin/bash
# Uji alur Work Assignment di server live (localhost:8010).
# Tidak mengirim WA, tidak mengubah data selain 1 row uji yang dihapus lagi.

set -u
BASE="http://127.0.0.1:8010"
JAR=/tmp/wa_live_jar.txt
rm -f "$JAR"

echo "1. GET login"
curl -s -c "$JAR" -o /tmp/wa_login.html -w "   HTTP %{http_code}  %{time_total}s\n" -m 60 "$BASE/work-assignment/login"

TOKEN=$(grep -oE 'name="_token" value="[^"]+"' /tmp/wa_login.html | head -1 | sed 's/.*value="//;s/"//')
echo "   csrf: ${TOKEN:0:10}..."

NPK="C-00825"
# Password login manual = tanggal lahir format ymd (lihat WorkAssignmentController::verifyManual).
# Ambil dari DB supaya tidak ada kredensial yang tersimpan di repo.
PASS=$(php8.3 artisan tinker --execute="
echo date('ymd', strtotime(DB::connection('cii')->table('PKWT')->where('NPK','$NPK')->value('TGLLAHIR')));" 2>/dev/null | tail -1)

echo "2. POST verify-manual (NPK $NPK)"
curl -s -b "$JAR" -c "$JAR" -o /dev/null -w "   HTTP %{http_code} -> %{redirect_url}  %{time_total}s\n" -m 120 \
  -X POST "$BASE/work-assignment/verify-manual" \
  --data-urlencode "_token=$TOKEN" \
  --data-urlencode "npk=$NPK" \
  --data-urlencode "password=$PASS"

echo "3. GET dashboard"
curl -s -b "$JAR" -c "$JAR" -o /tmp/wa_dash.html -w "   HTTP %{http_code}  %{size_download} bytes  %{time_total}s\n" -m 120 "$BASE/work-assignment"

echo "4. GET form"
curl -s -b "$JAR" -c "$JAR" -o /tmp/wa_form.html -w "   HTTP %{http_code}  %{size_download} bytes  %{time_total}s\n" -m 120 "$BASE/work-assignment/form"

echo
echo "=== penanda dashboard ==="
grep -o "Pengajuan Dinas Luar Saya" /tmp/wa_dash.html | head -1
grep -o "Belum ada pengajuan" /tmp/wa_dash.html | head -1
grep -o "Buat Pengajuan" /tmp/wa_dash.html | head -1

echo "=== penanda form ==="
grep -o "Data pemohon terisi otomatis" /tmp/wa_form.html | head -1
grep -o "Tambah Tanggal" /tmp/wa_form.html | head -1
grep -c "Cari" /tmp/wa_form.html | sed 's/^/   jumlah "Cari": /'