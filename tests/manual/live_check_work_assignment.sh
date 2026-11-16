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

echo "2. POST verify-manual (NPK C-00825)"
curl -s -b "$JAR" -c "$JAR" -o /dev/null -w "   HTTP %{http_code} -> %{redirect_url}  %{time_total}s\n" -m 120 \
  -X POST "$BASE/work-assignment/verify-manual" \
  --data-urlencode "_token=$TOKEN" \
  --data-urlencode "npk=C-00825" \
  --data-urlencode "password=050113"

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