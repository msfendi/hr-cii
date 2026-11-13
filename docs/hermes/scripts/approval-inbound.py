#!/usr/bin/env python3
"""
Messaging layer (Hermes side) — inbound approval decision.

Opsi C decoupling:
  - Laravel (business logic) memproses keputusan; messaging layer hanya relay.
  - Script ini: POST decision ke /api/approval/decision, lalu kirim balasan
    dari response ke approver via WA bridge.

Usage:
  python3 approval-inbound.py --phone 6281234567890 --message "/approve 849"
  python3 approval-inbound.py --phone 6281234567890 --decision approve --request-id 849
  python3 approval-inbound.py --phone 6281234567890 --message "/tolak 849 alasan" --dry-run

Env:
  HR_CII_API_URL      base URL Laravel API (wajib)
  APPROVAL_API_TOKEN  bearer token (wajib)
  WA_BRIDGE_URL       WA bridge base URL (default http://127.0.0.1:3000)
"""

import argparse
import json
import os
import sys
import urllib.error
import urllib.request

# Normalisasi nomor WA — harus sinkron dengan PhoneFormatter.php (Laravel).
sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))
try:
    from approval_phone import normalize_phone, to_chat_id
except ImportError:
    def normalize_phone(value):
        digits = "".join(ch for ch in str(value or "") if ch.isdigit())
        return digits

    def to_chat_id(value):
        digits = normalize_phone(value)
        return f"{digits}@s.whatsapp.net" if digits else ""

# Load config dari ~/.hermes/approval-wa.env (portable lintas server)
sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))
try:
    from approval_env import load_env
    load_env()
except ImportError:
    pass

API_URL = os.environ.get("HR_CII_API_URL", "").rstrip("/")
API_TOKEN = os.environ.get("APPROVAL_API_TOKEN", "")
BRIDGE_URL = os.environ.get("WA_BRIDGE_URL", "http://127.0.0.1:3000").rstrip("/")


def log(msg):
    print(f"[approval-inbound] {msg}", flush=True)


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


def post_decision(phone, message=None, decision=None, request_id=None, reason=None):
    """POST decision ke Laravel. Returns (status, body)."""
    if not API_URL or not API_TOKEN:
        log("ERROR: HR_CII_API_URL dan APPROVAL_API_TOKEN wajib diset")
        sys.exit(2)

    payload = {"sender": phone}
    if message:
        payload["message"] = message
    if decision:
        payload["decision"] = decision
    if request_id:
        payload["request_id"] = request_id
    if reason:
        payload["reason"] = reason

    return _http(
        "POST",
        f"{API_URL}/api/approval/decision",
        payload=payload,
        headers={"Authorization": f"Bearer {API_TOKEN}"},
    )


def send_wa(phone, message):
    """Kirim balasan ke approver via WA bridge."""
    if not message:
        log("no reply text — nothing to send")
        return False
    chat_id = to_chat_id(phone)
    if not chat_id:
        log(f"FAILED reply: nomor tidak bisa dinormalisasi ({phone})")
        return False
    status, body = _http("POST", f"{BRIDGE_URL}/send", {"chatId": chat_id, "message": message}, timeout=30)
    if status == 200 and isinstance(body, dict) and body.get("success"):
        log(f"reply sent to {chat_id}")
        return True
    log(f"FAILED reply to {chat_id}: HTTP {status} {body}")
    return False


def main():
    p = argparse.ArgumentParser()
    p.add_argument("--phone", required=True, help="nomor WA approver (raw)")
    p.add_argument("--message", help="pesan asli dari approver, mis. '/approve 849'")
    p.add_argument("--decision", help="approve | reject | revert (alternatif dari --message)")
    p.add_argument("--request-id", type=int, help="ID pengajuan (dipakai bila --decision)")
    p.add_argument("--reason", help="alasan (untuk reject)")
    p.add_argument("--dry-run", action="store_true", help="tidak kirim balasan ke WA")
    args = p.parse_args()

    if not args.message and not (args.decision and args.request_id):
        log("ERROR: butuh --message atau (--decision + --request-id)")
        return 2

    # Normalisasi nomor pengirim dulu (bisa "+6012…", "0812…", atau JID).
    sender = normalize_phone(args.phone)
    if not sender:
        log(f"ERROR: nomor pengirim tidak bisa dinormalisasi: {args.phone!r}")
        return 2

    status, body = post_decision(
        sender,
        message=args.message,
        decision=args.decision,
        request_id=args.request_id,
        reason=args.reason,
    )

    if status != 200:
        detail = body.get("message") if isinstance(body, dict) else str(body)
        log(f"decision failed: HTTP {status}")
        log(str(detail)[:300])
        return 1

    reply = body.get("reply") if isinstance(body, dict) else None
    decision_status = body.get("status") if isinstance(body, dict) else None

    log(f"decision processed: status={decision_status} request_id={body.get('request_id') if isinstance(body, dict) else '?'}")
    if reply:
        log(f"reply:\n{reply}")

    if args.dry_run:
        log("[DRY RUN] reply not sent")
        return 0

    if reply:
        send_wa(sender, reply)

    return 0 if decision_status == "ok" else 1


if __name__ == "__main__":
    sys.exit(main())
