#!/bin/bash
# Uji halaman approval + recap di browser headless dengan session auth asli.
# Tangkap console error JS dan cek tabel ter-render.
set -u
cd /home/hermes/workspace/hr-cii
BASE="http://127.0.0.1:8010"
JAR=/tmp/wa_browser_jar.txt
rm -f "$JAR"

echo "=== 1. Siapkan pengajuan uji ==="
php8.3 artisan tinker --execute="
use App\Models\WorkAssignmentRequest;
use App\Services\WorkAssignment\ApprovalChainBuilder;
use Illuminate\Support\Facades\DB;
\$npk='C-00825';
WorkAssignmentRequest::where('npk',\$npk)->delete();
\$chain = ApprovalChainBuilder::build(
  DB::connection('cii')->table('BIODATA')->where('NPK',\$npk)->value('ID_DEPT'),
  DB::connection('cii')->table('BIODATA')->where('NPK',\$npk)->value('SECTION')
);
\$r = WorkAssignmentRequest::create([
  'token'=>'ZWABR'.substr(md5(uniqid('',true)),0,8),'npk'=>\$npk,
  'duty_dates'=>['2026-12-08','2026-12-09'],'start_date'=>'2026-12-08','end_date'=>'2026-12-09','total_days'=>2,
  'dinas_ke'=>'Kantor Pusat Jakarta','keperluan'=>'Uji browser approval work assignment',
  'jam_keluar'=>'08:00','rencana_kembali'=>'17:00','jam_kembali'=>null,
  'approval_chain'=>\$chain,'answers'=>[],'status'=>'pending','current_level'=>1,'void'=>'false','submitted_by'=>\$npk,
]);
echo 'id='.\$r->id.' L1='.\$chain[0]['npk'].PHP_EOL;
" 2>&1 | tail -1

echo "=== 2. Login dev sebagai approver L1 ==="
curl -s -c "$JAR" -b "$JAR" -o /tmp/devlogin.json -w "  login-as: %{http_code} " "$BASE/_dev/login-as?npk=C-00827"
cat /tmp/devlogin.json
echo

CHROME=/home/hermes/.cache/ms-playwright/chromium-1248/chrome-linux64/chrome

# 3. Approval page
echo "=== 3. Approval page ==="
timeout 90 "$CHROME" --headless --disable-gpu --no-sandbox --virtual-time-budget=8000 \
  --enable-logging=stderr --v=0 --dump-dom "$BASE/work-assignment/approval" > /tmp/appr_dom.html 2>/tmp/appr_err.txt
echo "  <td> count: $(grep -o '<td' /tmp/appr_dom.html | wc -l)"
echo "  'Kantor Pusat' muncul: $(grep -c 'Kantor Pusat' /tmp/appr_dom.html)"
echo "  'btn-approve' muncul: $(grep -c 'btn-approve' /tmp/appr_dom.html)"
echo "  --- JS errors ---"
grep -iE "Uncaught|TypeError|ReferenceError|is not defined|Requested unknown" /tmp/appr_err.txt | head -8

# 4. Recap page
echo "=== 4. Recap page ==="
timeout 90 "$CHROME" --headless --disable-gpu --no-sandbox --virtual-time-budget=8000 \
  --enable-logging=stderr --v=0 --dump-dom "$BASE/work-assignment/manage" > /tmp/recap_dom.html 2>/tmp/recap_err.txt
echo "  <td> count: $(grep -o '<td' /tmp/recap_dom.html | wc -l)"
echo "  'Kantor Pusat' muncul: $(grep -c 'Kantor Pusat' /tmp/recap_dom.html)"
echo "  --- JS errors ---"
grep -iE "Uncaught|TypeError|ReferenceError|is not defined|Requested unknown" /tmp/recap_err.txt | head -8

echo "=== 5. Screenshot ==="
timeout 90 "$CHROME" --headless --disable-gpu --no-sandbox --hide-scrollbars \
  --screenshot=/tmp/wa_appr_page.png --window-size=1500,900 "$BASE/work-assignment/approval" 2>/dev/null | grep -oE "[0-9]+ bytes written"
timeout 90 "$CHROME" --headless --disable-gpu --no-sandbox --hide-scrollbars \
  --screenshot=/tmp/wa_recap_page.png --window-size=1500,900 "$BASE/work-assignment/manage" 2>/dev/null | grep -oE "[0-9]+ bytes written"

echo "=== 6. Cleanup ==="
php8.3 artisan tinker --execute="
\$n = App\Models\WorkAssignmentRequest::where('npk','C-00825')->delete();
echo '  dihapus: '.\$n.PHP_EOL;" 2>&1 | tail -1