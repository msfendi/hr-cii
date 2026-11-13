#!/usr/bin/env python3
"""
Messaging layer (Hermes side) — outbound approval notification.

Opsi C decoupling:
  - Laravel (business logic) tidak pernah kirim WA.
  - Script ini ambil payload dari /api/approval/pending, lalu kirim ke WA bridge.
  - Throttle anti-ban di sini (satu responsibility).

Flow:
  1. GET {HR_CII_API_URL}/api/approval/pending  (bearer token)
  2. Untuk tiap item: kirim ke WA bridge dengan jitter + rate limit
  3. Kirim balasan konfirmasi ke approver via bridge (untuk /approve /tolak)

Usage:
  python3 approval-outbound.py                # sweep semua pending
  python3 approval-outbound.py --token <token> # trigger 1 pengajuan
  python3 approval-outbound.py --dry-run       # print saja, tidak kirim

Env:
  HR_CII_API_URL      base URL Laravel API (wajib, mis. http://192.168.0.68:8010)
  APPROVAL_API_TOKEN  bearer token (wajib)
  WA_BRIDGE_URL       WA bridge base URL (default http://127.0.0.1:3000)
  WA_JITTER_SECONDS   jitter base (default 15)
  WA_MIN_GAP_SECONDS  gap minimum antar pesan (default 15)
  WA_MAX_PER_MINUTE   burst guard (default 6)
"""

import json
import os
import random
import sys
import time
import urllib.error
import urllib.request

# Load config dari ~/.hermes/approval-wa.env (portable lintas server)
sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))
try:
    from approval_env import load_env
    load_env()
except ImportError:
    pass

# Normalisasi nomor WA — harus sinkron dengan PhoneFormatter.php (Laravel).
try:
    from approval_phone import normalize_phone, to_chat_id
except ImportError:
    def normalize_phone(value):
        digits = "".join(ch for ch in str(value or "") if ch.isdigit())
        return digits

    def to_chat_id(value):
        digits = normalize_phone(value)
        return f"{digits}@s.whatsapp.net" if digits else ""

API_URL = os.environ.get("HR_CII_API_URL", "").rstrip("/")
API_TOKEN = os.environ.get("APPROVAL_API_TOKEN", "")
BRIDGE_URL = os.environ.get("WA_BRIDGE_URL", "http://127.0.0.1:3000").rstrip("/")

JITTER = float(os.environ.get("WA_JITTER_SECONDS", "15"))
MIN_GAP = float(os.environ.get("WA_MIN_GAP_SECONDS", "15"))
MAX_PER_MINUTE = int(os.environ.get("WA_MAX_PER_MINUTE", "6"))

_last_send = 0.0
_recent = []


def log(msg):
    print(f"[approval-outbound] {msg}", flush=True)


def _http(method, url, payload=None, headers=None, timeout=30):
    """HTTP helper. Returns (status_code, body_dict_or_text)."""
    data = None
    hdrs = {"Accept": "application/json"}
    if headers:
        hdrs.update(headers)
    if payload is not None:
        data = json.dumps(payload).encode("utf-8")
        hdrs["Content-Type"] = "application/json"
    req = urllib.request.Request(url, data=data, headers=hdrs, method=method)
    try:
        with urllib.request.urlopen(req, timeout=timeout) as resp:
            body = resp.read().decode("utf-8", "replace")
            try:
                return resp.status, json.loads(body)
            except json.JSONDecodeError:
                return resp.status, body
    except urllib.error.HTTPError as e:
        body = e.read().decode("utf-8", "replace")
        try:
            return e.code, json.loads(body)
        except json.JSONDecodeError:
            return e.code, body
    except Exception as e:  # noqa: BLE001
        log(f"ERROR {method} {url}: {e}")
        return 0, {"error": str(e)}


def throttle():
    """Jitter + gap minimum + burst guard sebelum kirim."""
    global _last_send, _recent

    now = time.time()
    _recent = [t for t in _recent if (now - t) < 60]

    if MAX_PER_MINUTE > 0 and len(_recent) >= MAX_PER_MINUTE:
        wait_for = int(60 - (now - min(_recent))) + 1
        log(f"burst limit reached ({MAX_PER_MINUTE}/min), waiting {wait_for}s")
        time.sleep(wait_for)
        now = time.time()
        _recent = [t for t in _recent if (now - t) < 60]

    if _last_send:
        since = now - _last_send
        if since < MIN_GAP:
            wait_for = int(MIN_GAP - since) + 1
            log(f"gap guard, waiting {wait_for}s (since last {since:.1f}s)")
            time.sleep(wait_for)

    if JITTER > 0:
        wait_for = JITTER * (0.7 + random.random() * 0.6)
        log(f"jitter, waiting {wait_for:.1f}s")
        time.sleep(wait_for)

    _last_send = time.time()
    _recent.append(_last_send)


def send_wa(phone, message):
    """Kirim pesan ke WA bridge. Returns True on success."""
    chat_id = to_chat_id(phone)
    if not chat_id:
        log(f"FAILED send: nomor tidak bisa dinormalisasi ({phone})")
        return False
    status, body = _http("POST", f"{BRIDGE_URL}/send", {"chatId": chat_id, "message": message})
    if status == 200 and isinstance(body, dict) and body.get("success"):
        log(f"sent to {chat_id} (messageId={body.get('messageId')})")
        return True
    log(f"FAILED send to {chat_id}: HTTP {status} {body}")
    return False


def fetch_pending():
    """Ambil list pengajuan yang siap dikabari dari Laravel."""
    if not API_URL or not API_TOKEN:
        log("ERROR: HR_CII_API_URL dan APPROVAL_API_TOKEN wajib diset")
        sys.exit(2)
    status, body = _http(
        "GET",
        f"{API_URL}/api/approval/pending",
        headers={"Authorization": f"Bearer {API_TOKEN}"},
        timeout=60,
    )
    if status != 200:
        detail = body.get("message") if isinstance(body, dict) else str(body)
        log(f"ERROR: GET pending returned HTTP {status}: {str(detail)[:300]}")
        return []
    return body.get("items", []) if isinstance(body, dict) else []


def fetch_trigger(token):
    """Ambil payload 1 pengajuan (by token)."""
    if not API_URL or not API_TOKEN:
        log("ERROR: HR_CII_API_URL dan APPROVAL_API_TOKEN wajib diset")
        sys.exit(2)
    status, body = _http(
        "POST",
        f"{API_URL}/api/approval/trigger",
        payload={"token": token},
        headers={"Authorization": f"Bearer {API_TOKEN}"},
        timeout=60,
    )
    if status != 200:
        log(f"ERROR: POST trigger returned HTTP {status}: {body}")
        return []
    return body.get("items", []) if isinstance(body, dict) else []


def main():
    args = sys.argv[1:]
    dry_run = "--dry-run" in args
    token = None
    if "--token" in args:
        token = args[args.index("--token") + 1]

    if token:
        items = fetch_trigger(token)
    else:
        items = fetch_pending()

    if not items:
        log("no pending requests eligible for notification")
        return 0

    log(f"{len(items)} item(s) to notify")
    sent = 0
    failed = 0

    for item in items:
        phone = item.get("phone")
        message = item.get("message")
        request_id = item.get("request_id")
        approver = item.get("approver_name", "?")
        level = item.get("level")

        if not phone or not message:
            log(f"skip #{request_id}: incomplete payload")
            failed += 1
            continue

        log(f"#{request_id} → {approver} ({phone}) level={level}")

        if dry_run:
            log(f"[DRY RUN] would send:\n{message}\n")
            sent += 1
            continue

        throttle()
        if send_wa(phone, message):
            sent += 1
        else:
            failed += 1

    log(f"done — sent={sent} failed={failed}")
    return 0 if failed == 0 else 1


if __name__ == "__main__":
    sys.exit(main())
