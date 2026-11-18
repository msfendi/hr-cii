#!/usr/bin/env python3
"""
Hermes webhook handler — dipanggil saat Laravel trigger notifikasi.

Hermes Webhook platform memanggil script ini dengan payload JSON di stdin:
  { "token": "abc123", "trigger": "submit" | "approve" }

Script ini ambil payload notifikasi dari Laravel, lalu kirim ke WA approver
lewat WA bridge. Throttle anti-ban diterapkan di sini.

Output ke stdout = payload baru (replaces payload untuk prompt templating).
Kosong / [SILENT] / exit != 0 → webhook diabaikan.

Exit codes:
  0  = sukses (payload dikirim ke WA)
  10 = tidak ada yang perlu dikirim (tidak_eligible / tidak ada pending)
  1  = error
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
    print(f"[approval-webhook] {msg}", file=sys.stderr, flush=True)


def _http(method, url, payload=None, headers=None, timeout=60):
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
            log(f"gap guard, waiting {wait_for}s")
            time.sleep(wait_for)

    if JITTER > 0:
        wait_for = JITTER * (0.7 + random.random() * 0.6)
        log(f"jitter, waiting {wait_for:.1f}s")
        time.sleep(wait_for)

    _last_send = time.time()
    _recent.append(_last_send)


def send_wa(phone, message):
    chat_id = to_chat_id(phone)
    if not chat_id:
        log(f"FAILED send: nomor tidak bisa dinormalisasi ({phone})")
        return False
    status, body = _http("POST", f"{BRIDGE_URL}/send", {"chatId": chat_id, "message": message}, timeout=30)
    if status == 200 and isinstance(body, dict) and body.get("success"):
        log(f"sent to {chat_id} (messageId={body.get('messageId')})")
        return True
    log(f"FAILED send to {chat_id}: HTTP {status} {body}")
    return False


def fetch_items(token):
    """Ambil payload notifikasi untuk 1 token dari Laravel."""
    if not API_URL or not API_TOKEN:
        log("ERROR: HR_CII_API_URL dan APPROVAL_API_TOKEN wajib diset")
        return []

    status, body = _http(
        "POST",
        f"{API_URL}/api/approval/trigger",
        payload={"token": token},
        headers={"Authorization": f"Bearer {API_TOKEN}"},
    )
    if status != 200:
        detail = body.get("message") if isinstance(body, dict) else str(body)
        log(f"ERROR: POST trigger HTTP {status}: {str(detail)[:300]}")
        return []

    if not isinstance(body, dict):
        return []

    if body.get("status") == "not_eligible":
        log(f"token {token} not eligible: {body.get('reason')}")
        return []

    return body.get("items", [])


def main():
    try:
        raw = sys.stdin.read().strip()
    except Exception:  # noqa: BLE001
        raw = ""

    if not raw:
        log("empty payload — ignoring")
        return 10

    try:
        payload = json.loads(raw)
    except json.JSONDecodeError:
        log("invalid JSON payload — ignoring")
        return 10

    token = payload.get("token")
    trigger = payload.get("trigger", "unknown")
    if not token:
        log("no token in payload — ignoring")
        return 10

    log(f"trigger={trigger} token={token}")

    items = fetch_items(token)
    if not items:
        return 10

    sent = 0
    for item in items:
        phone = item.get("phone")
        message = item.get("message")
        request_id = item.get("request_id")
        approver = item.get("approver_name", "?")
        level = item.get("level")

        if not phone or not message:
            log(f"skip #{request_id}: incomplete payload")
            continue

        log(f"#{request_id} → {approver} ({phone}) level={level}")
        throttle()
        if send_wa(phone, message):
            sent += 1

    if sent == 0:
        return 10

    # Pesan sudah terkirim ke WA. Output [SILENT] supaya webhook dianggap
    # "ignored" → tidak memicu agent run / LLM call (zero cost).
    print("[SILENT]")
    return 0


if __name__ == "__main__":
    sys.exit(main())
