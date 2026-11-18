#!/usr/bin/env python3
"""Shared phone normalization for the approval messaging layer.

MUST stay in sync with ``app/Services/ApprovalWa/PhoneFormatter.php`` (Laravel).

Rules
------
1. JID form (``60127383232@s.whatsapp.net``) → digits only.
2. Leading ``+`` → international, country code preserved verbatim.
3. ``62…``  → already Indonesia.
4. ``0…``   → Indonesia local → ``62…``.
5. ``8…``   → Indonesia mobile if the operator prefix is known, else keep
   digits as-is (may be another country).
6. Anything else → digits only; Laravel remains the authority that decides
   whether the number matches an approver in ``PKWT``.

Result is always digits only, so it can be appended to ``@s.whatsapp.net``
to build a Baileys chat id.
"""

import re

# Operator prefixes of Indonesian mobile numbers (2 digits after the leading 8).
ID_MOBILE_PREFIXES = {
    "11", "12", "13", "14", "15", "16", "17", "18", "19",
    "21", "22", "23", "24", "25", "26", "27", "28", "29",
    "31", "32", "33", "38", "39",
    "51", "52", "53", "56", "57", "58", "59",
    "61", "62", "63", "65", "66", "67", "68", "69",
    "71", "72", "77", "78", "79",
    "81", "82", "83", "85", "87", "88", "89",
    "91", "92", "93", "95", "96", "97", "98", "99",
}

# Country calling codes (ISO 3166-1 alpha-2 → prefix), longest match first.
COUNTRY_CALLING_CODES = {
    "ID": "62", "MY": "60", "SG": "65", "TH": "66", "VN": "84",
    "PH": "63", "IN": "91", "PK": "92", "LK": "94", "MM": "95",
    "JP": "81", "KR": "82", "CN": "86", "HK": "852", "MO": "853",
    "TW": "886", "KH": "855", "LA": "856", "BN": "673", "AU": "61",
    "NZ": "64", "AE": "971", "SA": "966", "QA": "974", "KW": "965",
    "JO": "962", "IL": "972", "TR": "90", "US": "1", "GB": "44",
    "DE": "49", "FR": "33", "NL": "31", "BE": "32", "ES": "34",
    "IT": "39", "SE": "46", "NO": "47", "DK": "45", "FI": "358",
    "PL": "48", "CH": "41", "AT": "43", "PT": "351", "IE": "353",
    "RO": "40", "BR": "55", "AR": "54", "MX": "52", "ZA": "27",
    "EG": "20",
}


def normalize_phone(value):
    """Return digits-only international number, or '' when unusable.

    Examples
    --------
    >>> normalize_phone('+60127383232')
    '60127383232'
    >>> normalize_phone('081390207272')
    '6281390207272'
    >>> normalize_phone('84913662215')
    '84913662215'
    >>> normalize_phone('60127383232@s.whatsapp.net')
    '60127383232'
    """
    if value is None:
        return ""

    raw = str(value).strip()
    if not raw:
        return ""

    # JID form → digits.
    if "@" in raw:
        digits = re.sub(r"[^0-9]", "", raw)
        return digits if len(digits) >= 8 else ""

    # A PKWT cell may hold several numbers — take the first.
    if "/" in raw:
        parts = [p.strip() for p in raw.split("/") if p.strip()]
        if not parts:
            return ""
        raw = parts[0]

    has_plus = raw.startswith("+")
    digits = re.sub(r"[^0-9]", "", raw)
    if not digits:
        return ""

    # Explicit international → keep verbatim.
    if has_plus:
        return digits if _is_e164(digits) else ""

    if digits.startswith("62"):
        return digits if _is_e164(digits) else ""

    if digits.startswith("0"):
        local = "62" + digits.lstrip("0")
        return local if _is_indonesian(local) else ""

    if digits.startswith("8"):
        local = "62" + digits
        if len(digits) >= 10 and digits[1:3] in ID_MOBILE_PREFIXES:
            return local if _is_indonesian(local) else ""
        # Not an Indonesian operator prefix → might be another country.
        if _is_e164(digits) and _country_for(digits):
            return digits
        return ""

    if digits.startswith("6") and _is_e164(digits) and _country_for(digits):
        return digits

    country = _country_for(digits)
    if country and country != "ID" and _is_e164(digits):
        return digits

    return ""


def _is_e164(digits):
    """International format: 8-15 digits, no leading zero."""
    return bool(re.fullmatch(r"[1-9][0-9]{7,14}", digits or ""))


def _is_indonesian(digits):
    """Indonesian mobile: 62 + 8 + 8..12 digits."""
    return bool(re.fullmatch(r"628[0-9]{8,12}", digits or ""))


def _country_for(digits):
    """ISO country code for an international number, or None."""
    if not digits:
        return None

    for iso, prefix in sorted(
        COUNTRY_CALLING_CODES.items(), key=lambda kv: len(kv[1]), reverse=True
    ):
        if not digits.startswith(prefix):
            continue
        rest = digits[len(prefix):]
        if len(prefix) == 1 and len(rest) < 9:
            continue
        return iso

    return None


def to_chat_id(value):
    """Build a Baileys chat id ('<digits>@s.whatsapp.net') from any phone form."""
    digits = normalize_phone(value)
    return f"{digits}@s.whatsapp.net" if digits else ""


if __name__ == "__main__":
    # Tiny self-check: python3 approval_phone.py
    cases = {
        "+60127383232": "60127383232",
        "+84913662215": "84913662215",
        "+94772605624": "94772605624",
        "081390207272": "6281390207272",
        "84913662215": "84913662215",
        "60127383232@s.whatsapp.net": "60127383232",
        "081330555852/085229141066": "6281330555852",
    }
    failed = 0
    for raw, expected in cases.items():
        got = normalize_phone(raw)
        ok = got == expected
        failed += 0 if ok else 1
        print(f"{'OK ' if ok else 'FAIL'} {raw!r:38} -> {got!r} (expected {expected!r})")
    raise SystemExit(1 if failed else 0)
