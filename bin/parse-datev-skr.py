#!/usr/bin/env python3
"""DATEV SKR03/SKR04 PDF in Konten-JSON umwandeln."""
from __future__ import annotations

import json
import re
import sys
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
SOURCE = ROOT / "assets" / "data" / "source"

DEFAULTS = {
    "skr03": SOURCE / "Kontenrahmen_DATEV_SKR_3_2026.pdf",
    "skr04": SOURCE / "Kontenrahmen_DATEV_SKR_4_2026.pdf",
}
OUTPUTS = {
    "skr03": ROOT / "assets" / "data" / "skr03-datev.json",
    "skr04": ROOT / "assets" / "data" / "skr04-datev.json",
}

ACCOUNT_RE = re.compile(
    r"^(?:[RF]\s+)?(?:(?:[A-Z]{1,3}\s+){0,8})(\d{4})\s+(.+)$"
)

NOISE_PATTERNS = [
    re.compile(r"^-- \d+ of \d+ --$"),
    re.compile(r"^Seite \d+"),
    re.compile(r"^Art\.-Nr\."),
    re.compile(r"^Bilanz-"),
    re.compile(r"^Programm-"),
    re.compile(r"^Abschluss-"),
    re.compile(r"^zweck"),
    re.compile(r"^verbindung"),
    re.compile(r"^Posten"),
    re.compile(r"^DATEV-"),
    re.compile(r"^Standardkontenrahmen"),
    re.compile(r"^Gültig für"),
    re.compile(r"^Eigenformular"),
    re.compile(r"^GU$"),
    re.compile(r"^HB$"),
    re.compile(r"^KU$"),
    re.compile(r"^R$"),
    re.compile(r"^F$"),
    re.compile(r"^KU\s+\d"),
    re.compile(r"^\d{1,2}$"),
    re.compile(r"^-?\d{1,3}$"),
]


def extract_text(pdf_path: Path) -> str:
    from pypdf import PdfReader

    reader = PdfReader(str(pdf_path))
    return "\n".join((page.extract_text() or "") for page in reader.pages)


def is_noise(line: str) -> bool:
    if not line:
        return True
    for pattern in NOISE_PATTERNS:
        if pattern.search(line):
            return True
    if re.fullmatch(r"[A-Z]{1,3}", line):
        return True
    return False


def section_for(number: str, skr: str) -> str:
    first = int(number[0])
    if skr == "skr04":
        if first in (0, 1):
            return "aktiva"
        if first in (2, 3):
            return "passiva"
        if first in (4, 5, 6, 7):
            return "aufwand"
        return "ertrag"
    if first in (0, 1):
        return "aktiva"
    if first == 2:
        return "passiva"
    if first in (3, 4, 5, 6, 7):
        return "aufwand"
    return "ertrag"


def clean_name(name: str) -> str:
    name = name.replace("\u00ad", "")
    name = re.sub(r"\s+", " ", name).strip(" -")
    name = re.split(
        r"\s+(?:[RF]\s+)?(?:(?:[A-Z]{1,3}\s+){0,8})?\d{4}\s+",
        name,
    )[0]
    name = re.split(r"\s+\d{1,2}\)\s*", name)[0]
    name = re.sub(r"\s+AV\s+.*$", "", name)
    name = re.sub(r"\s+betriebliche\s*$", "", name, flags=re.IGNORECASE)
    return name.strip()


def should_continue_name(current_name: str, line: str) -> bool:
    if not line or is_noise(line):
        return False
    if ACCOUNT_RE.match(line):
        return False
    if re.search(r"\b\d{4}\b", line):
        return False
    if len(line) > 70:
        return False
    if current_name.endswith("-"):
        return True
    if current_name and current_name[-1].islower() and line[0].islower():
        return True
    return False


def join_continuation(current_name: str, line: str) -> str:
    line = line.strip()
    if current_name.endswith("-"):
        return current_name[:-1] + line
    return f"{current_name} {line}"


def parse_accounts(text: str, skr: str) -> list[dict]:
    accounts: dict[str, dict] = {}
    current_number: str | None = None
    current_name: str | None = None

    for raw_line in text.splitlines():
        line = raw_line.strip()
        if is_noise(line):
            continue

        match = ACCOUNT_RE.match(line)
        if match:
            if current_number and current_name:
                accounts[current_number] = {
                    "account_number": current_number,
                    "name": clean_name(current_name),
                    "account_class": current_number[0],
                    "section": section_for(current_number, skr),
                }
            current_number = match.group(1)
            current_name = match.group(2).strip()
            continue

        if current_number and current_name and should_continue_name(current_name, line):
            current_name = join_continuation(current_name, line)

    if current_number and current_name:
        accounts[current_number] = {
            "account_number": current_number,
            "name": clean_name(current_name),
            "account_class": current_number[0],
            "section": section_for(current_number, skr),
        }

    return [accounts[k] for k in sorted(accounts.keys())]


def parse_pdf(pdf_path: Path, skr: str, out_json: Path) -> int:
    accounts = parse_accounts(extract_text(pdf_path), skr)
    if len(accounts) < 100:
        print(f"{skr}: zu wenige Konten ({len(accounts)})", file=sys.stderr)
        return 2

    version = "2026" if "2026" in pdf_path.name else "2023"
    payload = {
        "meta": {
            "source": f"DATEV {skr.upper()} PDF",
            "source_file": pdf_path.name,
            "version": version,
            "account_count": len(accounts),
        },
        "konten": accounts,
    }
    out_json.write_text(json.dumps(payload, ensure_ascii=False, indent=2), encoding="utf-8")
    print(f"{skr.upper()}: {len(accounts)} Konten -> {out_json}")
    return 0


def main() -> int:
    args = [a.lower() for a in sys.argv[1:]]
    if not args or args[0] == "all":
        targets = ["skr03", "skr04"]
    elif args[0] in DEFAULTS:
        targets = [args[0]]
    else:
        print("Verwendung: python bin/parse-datev-skr.py [skr03|skr04|all]", file=sys.stderr)
        return 1

    exit_code = 0
    for skr in targets:
        pdf_path = DEFAULTS[skr]
        if not pdf_path.is_file():
            print(f"PDF fehlt: {pdf_path}", file=sys.stderr)
            exit_code = 1
            continue
        if parse_pdf(pdf_path, skr, OUTPUTS[skr]) != 0:
            exit_code = 2

    return exit_code


if __name__ == "__main__":
    raise SystemExit(main())
