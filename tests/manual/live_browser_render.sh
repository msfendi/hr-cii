#!/bin/bash
# Render halaman approval dengan session cookie asli.
# Chrome headless tak bisa set cookie dari luar, jadi:
#   1. buka halaman login dev (pakai ?redirect= agar browser ikut ke tujuan)
#   2. lalu dump DOM halaman tujuan dengan profile yang sama
set -u
BASE="http://127.0.0.1:8010"
CHROME=/home/hermes/.cache/ms-playwright/chromium-1248/chrome-linux64/chrome
PROFILE=/tmp/wa_chrome_profile
rm -rf "$PROFILE"; mkdir -p "$PROFILE"

echo "=== 1. login dev (simpan cookie di profile) ==="
timeout 60 "$CHROME" --headless --disable-gpu --no-sandbox \
  --user-data-dir="$PROFILE" --virtual-time-budget=5000 \
  --dump-dom "$BASE/_dev/login-as?npk=C-00827" > /tmp/dl_dom.html 2>/dev/null
echo "  body: $(head -c 120 /tmp/dl_dom.html | tr -d '\n')"

echo "=== 2. approval page (profile sama → cookie ikut) ==="
timeout 60 "$CHROME" --headless --disable-gpu --no-sandbox \
  --user-data-dir="$PROFILE" --virtual-time-budget=8000 \
  --enable-logging=stderr --v=0 --dump-dom "$BASE/work-assignment/approval" > /tmp/ap3.dom 2>/tmp/ap3.err
echo "  <td>: $(grep -o '<td' /tmp/ap3.dom | wc -l)"
echo "  'Kantor Pusat': $(grep -c 'Kantor Pusat' /tmp/ap3.dom)"
echo "  'btn-approve': $(grep -c 'btn-approve' /tmp/ap3.dom)"
echo "  JS error:"
grep -iE "Uncaught|TypeError|ReferenceError|is not defined|Requested unknown" /tmp/ap3.err | head -5 | sed 's/^/    /'

echo "=== 3. manage/recap page ==="
timeout 60 "$CHROME" --headless --disable-gpu --no-sandbox \
  --user-data-dir="$PROFILE" --virtual-time-budget=8000 \
  --enable-logging=stderr --v=0 --dump-dom "$BASE/work-assignment/manage" > /tmp/rc3.dom 2>/tmp/rc3.err
echo "  <td>: $(grep -o '<td' /tmp/rc3.dom | wc -l)"
echo "  'Kantor Pusat': $(grep -c 'Kantor Pusat' /tmp/rc3.dom)"
echo "  JS error:"
grep -iE "Uncaught|TypeError|ReferenceError|is not defined|Requested unknown" /tmp/rc3.err | head -5 | sed 's/^/    /'

echo "=== 4. screenshot ==="
timeout 60 "$CHROME" --headless --disable-gpu --no-sandbox --hide-scrollbars \
  --user-data-dir="$PROFILE" --virtual-time-budget=8000 \
  --screenshot=/tmp/wa_appr3.png --window-size=1500,900 "$BASE/work-assignment/approval" 2>/dev/null | grep -oE "[0-9]+ bytes written"
timeout 60 "$CHROME" --headless --disable-gpu --no-sandbox --hide-scrollbars \
  --user-data-dir="$PROFILE" --virtual-time-budget=8000 \
  --screenshot=/tmp/wa_recap3.png --window-size=1500,900 "$BASE/work-assignment/manage" 2>/dev/null | grep -oE "[0-9]+ bytes written"