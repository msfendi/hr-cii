"""Shared env loader for approval messaging-layer scripts (Hermes side).

Reads ~/.hermes/approval-wa.env if present, so the scripts work regardless of
which process invokes them (gateway webhook, cron, or manual shell).

File format: KEY=VALUE per line, # comments allowed. Existing os.environ values
take precedence (so inline overrides still work).
"""

import os
from pathlib import Path

_CONFIG_FILE = Path.home() / ".hermes" / "approval-wa.env"


def load_env(path: Path | None = None) -> None:
    """Populate os.environ from the config file (does not override existing vars)."""
    target = path or _CONFIG_FILE
    if not target.is_file():
        return

    try:
        raw = target.read_text(encoding="utf-8")
    except OSError:
        return

    for line in raw.splitlines():
        line = line.strip()
        if not line or line.startswith("#") or "=" not in line:
            continue
        key, _, value = line.partition("=")
        key = key.strip()
        value = value.strip().strip('"').strip("'")
        if key and key not in os.environ:
            os.environ[key] = value