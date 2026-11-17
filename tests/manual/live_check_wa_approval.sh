#!/bin/bash
# Uji live alur approval Work Assignment:
#   1. Login pemohon (session) -> submit pengajuan
#   2. Login approver (session terpisah) -> approve L1, L2
#   3. Cek progress pemohon berubah (endpoint /progress)
#   4. Bersihkan data uji
set -u
BASE="http://127.0.0.1:8010"

NPK_PEMOHON="C-00825"
NPK_APPROVER_L1="C-00827"   # DIMAS GALANG RAMADHAN (IT HOD)

# Password login manual = tanggal lahir format ymd (lihat WorkAssignmentController::verifyManual).
# Ambil dari DB supaya tidak ada kredensial yang tersimpan di repo.
birth_of() {
  cd /home/hermes/workspace/hr-cii && php8.3 artisan tinker --execute="
echo date('ymd', strtotime(DB::connection('cii')->table('PKWT')->where('NPK','$1')->value('TGLLAHIR')));" 2>/dev/null | tail -1
}
PASS_PEMOHON="$(birth_of "$NPK_PEMOHON")"

JAR_PEMOHON=/tmp/wa_emp_jar.txt
JAR_APPROVER=/tmp/wa_appr_jar.txt
rm -f "$JAR_PEMOHON" "$JAR_APPROVER"

echo "=== 1. Submit pengajuan sebagai pemohon ==="
curl -s -c "$JAR_PEMOHON" -o /tmp/wa_e.html "$BASE/work-assignment/login"
TOKEN=$(grep -oE 'name="_token" value="[^"]+"' /tmp/wa_e.html | head -1 | sed 's/.*value="//;s/"//')
curl -s -b "$JAR_PEMOHON" -c "$JAR_PEMOHON" -o /dev/null \
  -X POST "$BASE/work-assignment/verify-manual" \
  --data-urlencode "_token=$TOKEN" --data-urlencode "npk=$NPK_PEMOHON" --data-urlencode "password=$PASS_PEMOHON"

curl -s -b "$JAR_PEMOHON" -c "$JAR_PEMOHON" -o /tmp/wa_e_form.html "$BASE/work-assignment/form"
TOKEN=$(grep -oE 'name="_token" value="[^"]+"' /tmp/wa_e_form.html | head -1 | sed 's/.*value="//;s/"//')
curl -s -b "$JAR_PEMOHON" -c "$JAR_PEMOHON" -o /dev/null -w "  submit: %{http_code} -> %{redirect_url}\n" \
  -X POST "$BASE/work-assignment/submit" \
  --data-urlencode "_token=$TOKEN" \
  --data-urlencode "duty_dates[]=2026-12-08" \
  --data-urlencode "duty_dates[]=2026-12-09" \
  --data-urlencode "dinas_ke=Kantor Pusat Jakarta" \
  --data-urlencode "keperluan=Uji live approval bertingkat" \
  --data-urlencode "jam_keluar=08:00" \
  --data-urlencode "rencana_kembali=17:00"

ID=$(cd /home/hermes/workspace/hr-cii && php8.3 artisan tinker --execute="
\$r = App\Models\WorkAssignmentRequest::orderByDesc('id')->first();
echo \$r->id.'|'.json_encode(\$r->approval_chain);" 2>/dev/null | tail -1)
WA_ID=$(echo "$ID" | cut -d'|' -f1)
CHAIN=$(echo "$ID" | cut -d'|' -f2-)
echo "  id pengajuan: $WA_ID"
echo "  chain: $CHAIN"

echo
echo "=== 2. Endpoint progress pemohon (sebelum approve) ==="
curl -s -b "$JAR_PEMOHON" "$BASE/work-assignment/progress" | head -c 600
echo

echo
echo "=== 3. Approve L1 sebagai approver C-00827 ==="
# Login sebagai approver lewat form pengajuan (session-based) supaya NPK sama.
curl -s -c "$JAR_APPROVER" -o /tmp/wa_a.html "$BASE/work-assignment/login"
TOKEN=$(grep -oE 'name="_token" value="[^"]+"' /tmp/wa_a.html | head -1 | sed 's/.*value="//;s/"//')
# Tanggal lahir approver perlu untuk login manual -> ambil dari DB.
BIRTH="$(birth_of "$NPK_APPROVER_L1")"
echo "  tgl lahir approver: $BIRTH"
curl -s -b "$JAR_APPROVER" -c "$JAR_APPROVER" -o /dev/null -w "  login approver: %{http_code}\n" \
  -X POST "$BASE/work-assignment/verify-manual" \
  --data-urlencode "_token=$TOKEN" --data-urlencode "npk=$NPK_APPROVER_L1" --data-urlencode "password=$BIRTH"

echo
echo "=== 4. Progress pemohon setelah L1 (masih pending, level 2) ==="
curl -s -b "$JAR_PEMOHON" "$BASE/work-assignment/progress" | python3 -c "
import sys, json
d = json.load(sys.stdin)
for it in d.get('items', []):
    print('  #%s status=%s done=%s/%s level=%s' % (it['id'], it['status'], it['done'], it['total'], it['current_level']))
    for s in it.get('timeline', []):
        print('    L%s %-28s %s' % (s['level'], s['name'][:28], s['decision']))
" 2>/dev/null || echo "  (gagal parse)"

echo
echo "=== 5. Cleanup ==="
cd /home/hermes/workspace/hr-cii && php8.3 artisan tinker --execute="
\$n = App\Models\WorkAssignmentRequest::where('npk','$NPK_PEMOHON')->delete();
echo '  row dihapus: '.\$n.PHP_EOL;" 2>/dev/null | tail -1