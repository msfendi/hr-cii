#!/usr/bin/env python3
"""
Hermes webhook handler — dipanggil saat Laravel memicu notifikasi approval.

Menangani DUA jenis dokumen, dibedakan oleh field `doc`:

  cuti        → pengajuan cuti          (tabel leave_requests)
  izin-dinas  → Surat Ijin Dinas Luar   (tabel work_assignment_requests)

Hermes Webhook platform memanggil script ini dengan payload JSON di stdin:
  { "token": "abc123", "trigger": "submit" | "approve", "doc": "cuti" }

`doc` WAJIB diteruskan ke /api/approval/trigger. Tanpa itu Laravel menganggap
permintaan itu cuti, sehingga token Izin Dinas tidak ditemukan di
leave_requests → status "not_eligible" → pesan tidak pernah terkirim dan
tidak ada error yang terlihat.

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

# Label untuk log — supaya operator langsung tahu ini pesan dokumen yang mana.
DOC_LABELS = {
    "cuti": "CUTI",
    "izin-dinas": "IZIN DINAS",
}

DEFAULT_DOC = "cuti"

_last_send = 0.0
_recent = []


def log(msg):
    print(f"[approval-webhook] {msg}", file=sys.stderr, flush=True)


def normalize_doc(value):
    """Samakan penulisan doctype; tidak dikenal → cuti (perilaku lama)."""
    doc = str(value or "").strip().lower().replace("_", "-").replace(" ", "-")
    return doc if doc in DOC_LABELS else DEFAULT_DOC


def doc_label(doc):
    return DOC_LABELS.get(doc, doc.upper())


def _http(method, url, payload=None, headers=None, timeout=60):
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
    notifikasi teks.
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


def fetch_items(token, doc):
    """Ambil payload notifikasi untuk 1 token dari Laravel.

    `doc` menentukan Laravel memakai notifier yang mana (cuti vs izin dinas).
    """
    if not API_URL or not API_TOKEN:
        log("ERROR: HR_CII_API_URL dan APPROVAL_API_TOKEN wajib diset")
        return []

    status, body = _http(
        "POST",
        f"{API_URL}/api/approval/trigger",
        payload={"token": token, "doc": doc},
        headers={"Authorization": f"Bearer {API_TOKEN}"},
    )
    if status != 200:
        detail = body.get("message") if isinstance(body, dict) else str(body)
        log(f"ERROR: POST trigger ({doc}) HTTP {status}: {str(detail)[:300]}")
        return []

    if not isinstance(body, dict):
        return []

    if body.get("status") == "not_eligible":
        log(f"[{doc_label(doc)}] token {token} not eligible: {body.get('reason')}")
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

    raw_doc = payload.get("doc")
    doc = normalize_doc(raw_doc)

    if not raw_doc:
        # Laravel versi lama belum mengirim `doc`. Dianggap cuti supaya perilaku
        # lama tidak berubah, tapi dicatat lewat peringatan agar ketahuan kalau
        # ini sebenarnya pengajuan Izin Dinas.
        log("PERINGATAN: payload tanpa 'doc' → dianggap CUTI. "
            "Pastikan Laravel sudah diperbarui untuk dokumen izin-dinas.")

    log(f"[{doc_label(doc)}] trigger={trigger} token={token}")

    items = fetch_items(token, doc)
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

        log(f"[{doc_label(doc)}] #{request_id} → {approver} ({phone}) level={level}")
        throttle()
        if send_wa(phone, message):
            sent += 1
            send_attachments(item, phone)

    if sent == 0:
        return 10

    # Pesan sudah terkirim ke WA. Output [SILENT] supaya webhook dianggap
    # "ignored" → tidak memicu agent run / LLM call (zero cost).
    print("[SILENT]")
    return 0


if __name__ == "__main__":
    sys.exit(main())
