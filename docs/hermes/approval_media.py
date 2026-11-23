#!/usr/bin/env python3
"""Lampiran pengajuan cuti -> kirim ke WhatsApp lewat bridge Hermes.

Aturan produk:
  * ``image/*``  -> dikirim apa adanya sebagai foto
  * ``pdf``      -> dirender jadi PNG (halaman 1) dulu, baru dikirim
  * lainnya      -> dikirim sebagai dokumen

Payload Laravel (``ApprovalApiPayload::attachmentsFor()``) menyediakan::

    "attachments": [{"name": ..., "path": ..., "mime": ..., "size": ...,
                     "url": ..., "kind": "image|document"}]

Modul ini yang menentukan bentuk kirim; Laravel tidak perlu tahu detail bridge.
Semua kegagalan lampiran bersifat non-fatal: pesan teks tetap terkirim.
"""

from __future__ import annotations

import mimetypes
import os
import re
import shutil
import subprocess
import tempfile
import urllib.parse
import urllib.request
import uuid

MAX_BYTES = int(os.environ.get("APPROVAL_ATTACHMENT_MAX_BYTES", str(8 * 1024 * 1024)))
MAX_FILES = int(os.environ.get("APPROVAL_MAX_ATTACHMENTS", "3"))
PDF_DPI = int(os.environ.get("APPROVAL_PDF_DPI", "150"))
TIMEOUT = int(os.environ.get("APPROVAL_ATTACHMENT_TIMEOUT", "45"))
CAPTION_MAX = 900
MIN_RENDER_BYTES = 1000

IMAGE_EXT = (".jpg", ".jpeg", ".png", ".webp")


class AttachmentError(Exception):
    """Lampiran tidak bisa disiapkan untuk dikirim."""


def _log_default(msg):
    print(f"[approval_media] {msg}", flush=True)


def _safe_name(name, default="lampiran"):
    base = os.path.basename(str(name or "").replace("\\", "/"))
    base = re.sub(r"[^A-Za-z0-9._-]+", "_", base).strip("._")
    return base or default


def _fetch(url, dest):
    """Unduh ``url`` ke ``dest``. Returns (mime, size)."""
    req = urllib.request.Request(url, headers={"User-Agent": "hermes-approval/1.0"})
    with urllib.request.urlopen(req, timeout=TIMEOUT) as resp:
        ctype = (resp.headers.get("Content-Type") or "").split(";")[0].strip().lower()
        data = resp.read(MAX_BYTES + 1)
    if len(data) > MAX_BYTES:
        raise AttachmentError(f"file terlalu besar (> {MAX_BYTES} byte)")
    if not data:
        raise AttachmentError("file kosong")
    with open(dest, "wb") as fh:
        fh.write(data)
    return ctype, len(data)


def _render_pdf(src, dest, log=_log_default):
    """Render halaman 1 PDF jadi PNG. Coba pymupdf -> pdftoppm -> ImageMagick."""
    pymupdf = None
    try:
        import pymupdf as pymupdf  # 1.24+ pakai nama ini
    except ImportError:
        try:
            import fitz as pymupdf  # nama lama
        except ImportError:
            pymupdf = None

    if pymupdf is not None:
        try:
            doc = pymupdf.open(src)
            try:
                if doc.page_count < 1:
                    raise AttachmentError("pdf tidak punya halaman")
                zoom = PDF_DPI / 72.0
                pix = doc.load_page(0).get_pixmap(matrix=pymupdf.Matrix(zoom, zoom), alpha=False)
                pix.save(dest)
            finally:
                doc.close()
            if os.path.getsize(dest) > MIN_RENDER_BYTES:
                return dest
        except Exception as exc:  # noqa: BLE001
            log(f"pymupdf gagal: {exc} — coba pdftoppm/imagemagick")

    if shutil.which("pdftoppm"):
        prefix = dest[:-4] + "-ppm"
        proc = subprocess.run(
            ["pdftoppm", "-r", str(PDF_DPI), "-f", "1", "-l", "1", "-png", "-singlefile", src, prefix],
            capture_output=True,
            text=True,
        )
        produced = prefix + ".png"
        if proc.returncode == 0 and os.path.exists(produced) and os.path.getsize(produced) > MIN_RENDER_BYTES:
            os.replace(produced, dest)
            return dest

    for exe in ("magick", "convert"):
        if shutil.which(exe):
            proc = subprocess.run(
                [exe, "-density", str(PDF_DPI), f"{src}[0]", "-background", "white",
                 "-alpha", "remove", "-alpha", "off", dest],
                capture_output=True,
                text=True,
            )
            if proc.returncode == 0 and os.path.exists(dest) and os.path.getsize(dest) > MIN_RENDER_BYTES:
                return dest

    raise AttachmentError("pdf tidak bisa dirender (pymupdf/pdftoppm/imagemagick gagal)")


def prepare(attachment, workdir, log=_log_default):
    """Siapkan satu lampiran payload jadi berkas yang siap dikirim.

    Returns dict(path, media_type, file_name) atau None kalau tidak ada URL.
    """
    url = str(attachment.get("url") or "").strip()
    if not url.lower().startswith(("http://", "https://")):
        raise AttachmentError("url lampiran bukan http(s)")

    name = _safe_name(attachment.get("name") or os.path.basename(urllib.parse.urlparse(url).path))
    dest = os.path.join(workdir, f"{uuid.uuid4().hex[:8]}-{name}")

    ctype, size = _fetch(url, dest)
    mime = ctype or str(attachment.get("mime") or "") or (mimetypes.guess_type(name)[0] or "")
    lower = name.lower()
    log(f"lampiran {name} ({size} B, {mime or 'mime?'})")

    if mime.startswith("image/") or lower.endswith(IMAGE_EXT):
        return {"path": dest, "media_type": "image", "file_name": name}

    if mime == "application/pdf" or lower.endswith(".pdf"):
        png = dest.rsplit(".", 1)[0] + ".png"
        rendered = _render_pdf(dest, png, log=log)
        log(f"pdf {name} -> {os.path.basename(rendered)} ({os.path.getsize(rendered)} B)")
        return {"path": rendered, "media_type": "image", "file_name": os.path.basename(rendered)}

    return {"path": dest, "media_type": "document", "file_name": name}


def send_all(attachments, chat_id, send_media, caption="", workdir=None, log=_log_default):
    """Kirim semua lampiran. Returns (ok, fail). Tidak pernah melempar exception."""
    items = [a for a in (attachments or []) if isinstance(a, dict)]
    if not items or not chat_id:
        return 0, 0
    if len(items) > MAX_FILES:
        log(f"{len(items)} lampiran — hanya {MAX_FILES} pertama yang dikirim")
        items = items[:MAX_FILES]

    tmp = workdir or tempfile.mkdtemp(prefix="approval_att_")
    ok = fail = 0
    try:
        for idx, attachment in enumerate(items, 1):
            try:
                prep = prepare(attachment, tmp, log=log)
            except Exception as exc:  # noqa: BLE001
                fail += 1
                log(f"lampiran #{idx} dilewati: {exc}")
                continue
            cap = caption if idx == 1 else ""
            if len(cap) > CAPTION_MAX:
                cap = cap[: CAPTION_MAX - 1] + "…"
            try:
                sent = send_media(
                    chat_id,
                    prep["path"],
                    media_type=prep["media_type"],
                    caption=cap,
                    file_name=prep["file_name"],
                )
            except Exception as exc:  # noqa: BLE001
                fail += 1
                log(f"lampiran #{idx} gagal kirim: {exc}")
                continue
            if sent:
                ok += 1
            else:
                fail += 1
    finally:
        if workdir is None:
            shutil.rmtree(tmp, ignore_errors=True)
    return ok, fail


__all__ = ["AttachmentError", "prepare", "send_all"]