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
  APPROVAL_SEND_ATTACHMENT  1 (default) kirim lampiran pengajuan; 0 matikan
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
# Auth bridge WA: bridge baru menolak request tanpa Bearer token (lihat wa_bridge_auth.py).
try:
    from wa_bridge_auth import bridge_auth_headers
except ImportError:
    def bridge_auth_headers():
        return {}

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
    if BRIDGE_URL and url.startswith(BRIDGE_URL):
        hdrs.update(bridge_auth_headers())
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


def send_media_chat(chat_id, file_path, media_type="image", caption="", file_name=None):
    """Kirim media ke WA bridge (POST /send-media). chat_id sudah ter-resolve."""
    if not chat_id:
        return False
    payload = {"chatId": chat_id, "filePath": file_path, "mediaType": media_type}
    if caption:
        payload["caption"] = caption
    if file_name:
        payload["fileName"] = file_name
    status, body = _http("POST", f"{BRIDGE_URL}/send-media", payload, timeout=120)
    if status == 200 and isinstance(body, dict) and body.get("success"):
        log(f"sent media {os.path.basename(file_path)} to {chat_id} (messageId={body.get('messageId')})")
        return True
    log(f"FAILED send media to {chat_id}: HTTP {status} {body}")
    return False


def send_attachments(item, phone):
    """Kirim lampiran pengajuan setelah pesan teks.

    Gambar dikirim apa adanya, PDF dirender jadi PNG dulu (aturan produk),
    tipe lain dikirim sebagai dokumen. Kegagalan lampiran tidak menggagalkan
    notifikasi teks — approver tetap menerima perintah approve/tolak.
    """
    attachments = item.get("attachments") or []
    if not attachments:
        return 0, 0
    if os.environ.get("APPROVAL_SEND_ATTACHMENT", "1") == "0":
        log("APPROVAL_SEND_ATTACHMENT=0 — lampiran dilewati")
        return 0, 0
    chat_id = to_chat_id(phone)
    if not chat_id:
        return 0, 0
    try:
        from approval_media import send_all
    except ImportError as exc:
        log(f"approval_media tidak tersedia ({exc}) — lampiran dilewati")
        return 0, 0
    caption = f"Lampiran pengajuan #{item.get('request_id')}"
    ok, fail = send_all(attachments, chat_id, send_media_chat, caption=caption, log=log)
    log(f"lampiran #{item.get('request_id')}: ok={ok} gagal={fail}")
    return ok, fail


def fetch_pending(doc="cuti"):
    """Ambil list pengajuan yang siap dikabari dari Laravel.

    `doc` memilih jenis dokumen: cuti atau izin-dinas. Keduanya punya
    struktur data berbeda, jadi Laravel yang menyiapkan payload-nya.
    """
    if not API_URL or not API_TOKEN:
        log("ERROR: HR_CII_API_URL dan APPROVAL_API_TOKEN wajib diset")
        sys.exit(2)
    status, body = _http(
        "GET",
        f"{API_URL}/api/approval/pending?doc={doc}",
        headers={"Authorization": f"Bearer {API_TOKEN}"},
        timeout=60,
    )
    if status != 200:
        detail = body.get("message") if isinstance(body, dict) else str(body)
        log(f"ERROR: GET pending ({doc}) returned HTTP {status}: {str(detail)[:300]}")
        return []
    return body.get("items", []) if isinstance(body, dict) else []


def fetch_trigger(token, doc="cuti"):
    """Ambil payload 1 pengajuan (by token)."""
    if not API_URL or not API_TOKEN:
        log("ERROR: HR_CII_API_URL dan APPROVAL_API_TOKEN wajib diset")
        sys.exit(2)
    status, body = _http(
        "POST",
        f"{API_URL}/api/approval/trigger",
        payload={"token": token, "doc": doc},
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
    doc = "cuti"          # default: cuti — menjaga perilaku lama tetap jalan
    doc_was_given = False

    if "--token" in args:
        token = args[args.index("--token") + 1]
    if "--doc" in args:
        doc = args[args.index("--doc") + 1]
        doc_was_given = True

    if token:
        items = fetch_trigger(token, doc)
    elif doc_was_given:
        items = fetch_pending(doc)
    else:
        # Tanpa --doc: sapu semua jenis dokumen, supaya Izin Dinas tidak
        # terlewat hanya karena cron belum diubah.
        items = []
        for _doc in ("cuti", "izin-dinas"):
            items.extend(fetch_pending(_doc))

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
            _atts = item.get("attachments") or []
            if _atts:
                log("[DRY RUN] + %d lampiran: %s" % (
                    len(_atts), ", ".join(str(a.get("name") or a.get("url")) for a in _atts)))
            sent += 1
            continue

        throttle()
        if send_wa(phone, message):
            sent += 1
            send_attachments(item, phone)
        else:
            failed += 1

    log(f"done — sent={sent} failed={failed}")
    return 0 if failed == 0 else 1


if __name__ == "__main__":
    sys.exit(main())
