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

NEW_ACCOUNT_LINE = re.compile(
    r"^(In |Fertige |Unfertige |Sonstige |Verbindlichkeiten |Forderungen |Bestands|"
    r"Geleistete |Abziehbare |Andere |Restlaufzeit |Aufwendungen |Erlöse |Anlagen|"
    r"Technische |Grundst|Rückstellungen |Vermögens|Aktivierte |Gegenkonto |"
    r"Innergemeinschaft|Nachlässe |Zölle |Mietleasing |Abschreibungen )",
    re.IGNORECASE,
)

COMPLETE_ENDINGS = (
    "Aufträge",
    "Bauaufträge",
    "Waren",
    "Leistungen",
    "Erzeugnisse",
    "Eigenleistungen",
    "(Bestand)",
    "Umsatzsteuer",
    "Vorsteuer",
    "Verpflichtungen",
    "Rückstellungen",
    "Kontokorrent",
    "Finanzdisposition",
    "Umsatzsteuerlager",
    "Konten",
    "EÜR)",
)

INCOMPLETE_ENDINGS = {
    "befindliche",
    "unfertige",
    "andere",
    "sonstige",
    "gewerbliche",
    "immaterielle",
    "technische",
    "zahlungen",
    "ohne",
    "nach",
    "für",
    "mit",
    "und",
    "der",
    "die",
    "das",
    "an",
    "auf",
    "in",
    "zum",
    "zur",
    "gegenüber",
    "einschließlich",
    "entwicklung",
    "verträgen",
    "lieferungen",
    "gegenständen",
    "geschäfts-",
    "roh-",
    "rahmen",
    "bürgschaften",
    "stillen",
    "aktivierte",
    "altersversorgung",
    "unternehmers",
    "investitionen",
    "allgemeinen",
    "ermäßigten",
    "steuerpflichtigen",
    "innergemeinschaftliche",
    "ausführung",
    "arbeit",
    "fremd",
    "gelieferte",
    "bezogene",
    "bau-",
}

BLEED_MARKERS = [
    r"\s+mögensgegenstände\b.*$",
    r"\s+bindlichkeiten\b.*$",
    r"\s+gensgegenstände\b.*$",
    r"\s+gen-stände\b.*$",
    r"\s+genstände\b.*$",
    r"\s+oder Sonstige\b.*$",
    r"\s+oder Andere\b.*$",
    r"\s+oder Verbindlichkeiten\b.*$",
    r"\s+oder Forderungen\b.*$",
    r"\s+oder bindlichkeiten\b.*$",
    r"\s+nisse und\b.*$",
    r"\s+träge che Aufträge\b.*$",
    r"\s+zahlungen und\b.*$",
    r"\s+ten gegenüber\b.*$",
    r"\s+ten aus Lieferungen\b.*$",
    r"\s+runger gegen\b.*$",
    r"\s+lichbkeiten\b.*$",
    r"\s+schiedsbetrag\b.*$",
    r"\s+stellungen\b.*$",
    r"\s+In Arbeit be-.*$",
    r"\s+für aus Lieferungen und Leistungen oder Sonstige\b.*$",
    r"\s+gegenüber Gesellschaftern oder\b.*$",
    r"\s+haben, Guthaben bei\b.*$",
]

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


def name_looks_complete(name: str) -> bool:
    name = name.strip()
    if not name or name.endswith("-"):
        return False
    for end in COMPLETE_ENDINGS:
        if name.endswith(end):
            return True
    if re.search(r"\d\s*%\s*Vorsteuer\s*$", name, flags=re.IGNORECASE):
        return "Umsatzsteuer" in name
    if re.search(
        r"(Vorsteuer|Umsatzsteuer)\s*$", name, flags=re.IGNORECASE
    ) and not re.search(r"\b(nach|ohne|für|und)$", name, flags=re.IGNORECASE):
        return True
    return False


def ends_incomplete(name: str) -> bool:
    stripped = name.strip()
    if stripped.endswith("-"):
        return True
    words = stripped.split()
    if not words:
        return True
    last = words[-1].lower().rstrip(".,;")
    if last in INCOMPLETE_ENDINGS:
        return True
    return len(last) <= 3


def repair_account_name(name: str) -> str:
    name = name.replace("\u00ad", "")
    name = re.sub(r"\s+", " ", name).strip(" -")

    name = re.sub(r"\bHilfsund\b", "Hilfs- und", name, flags=re.IGNORECASE)
    name = re.sub(r"\bf\s+ür\b", "für", name, flags=re.IGNORECASE)
    name = re.sub(r"\bo\s+hne\b", "ohne", name, flags=re.IGNORECASE)
    name = re.sub(r"\bVorsteue\s+r\b", "Vorsteuer", name, flags=re.IGNORECASE)
    name = re.sub(r"\bAn\s+dere\b", "Andere", name)
    name = re.sub(r"^B\s+estandsveränderungen", "Bestandsveränderungen", name, flags=re.IGNORECASE)
    name = re.sub(
        r"^Bestandsveränderungen\s*-\s*",
        "Bestandsveränderungen ",
        name,
        flags=re.IGNORECASE,
    )
    name = re.sub(r"Vorsteuerabzug\d+\)", "Vorsteuerabzug", name, flags=re.IGNORECASE)
    name = re.sub(r"\s+\d{1,2}\)\s*$", "", name)
    name = re.sub(r"\begenständen\b", "Gegenständen", name, flags=re.IGNORECASE)
    name = re.sub(r"\bWar en\b", "Waren", name)
    name = re.sub(
        r"aus tungsverbindlichkeiten",
        "aus Käufen von Finanzanlagen bei Leistungsverbindlichkeiten",
        name,
        flags=re.IGNORECASE,
    )
    name = re.sub(
        r"in Ausführung befindlicher$",
        "in Ausführung befindliche Bauaufträge",
        name,
        flags=re.IGNORECASE,
    )
    name = re.sub(r"Gesamthand\d+\)", "Gesamthand", name, flags=re.IGNORECASE)
    name = re.sub(r"\bG K\b", "§ 7g EStG", name)
    name = re.sub(
        r" in Entwicklung Sachanlagen\b",
        " in Entwicklung",
        name,
        flags=re.IGNORECASE,
    )
    name = re.sub(
        r" in Entwicklung Geschäfts\b",
        " in Entwicklung",
        name,
        flags=re.IGNORECASE,
    )
    name = re.sub(
        r"\s+HGB Sonstige Vermögensgegenstände\b.*$",
        "",
        name,
        flags=re.IGNORECASE,
    )
    name = re.sub(
        r"nach und ähnliche\b",
        "nach § 231 Abs. 2 Satz 2 HGB Zinsen und ähnliche Erträge",
        name,
        flags=re.IGNORECASE,
    )
    name = re.sub(
        r"(ohne|mit)\s+sässigen Unternehmers\s+(ohne|mit)\s+",
        r"\1 ",
        name,
        flags=re.IGNORECASE,
    )
    name = re.sub(r"\s+gen$", "", name, flags=re.IGNORECASE)
    name = re.sub(r"RohHilfs-", "Roh-, Hilfs-", name, flags=re.IGNORECASE)
    name = re.sub(
        r"Forderungen nach § 11 Abs\. 1 Forderungen",
        "Forderungen nach § 11 Abs. 1 EStG",
        name,
        flags=re.IGNORECASE,
    )
    name = re.sub(r"verbundenen/$", "verbundenen Unternehmen", name, flags=re.IGNORECASE)

    for prefix in ("Erlösschmälerungen", "Unentgeltliche Zuwendung"):
        match = re.match(
            rf"^({re.escape(prefix)}.+?)(?:\s+{re.escape(prefix)})",
            name,
            flags=re.IGNORECASE,
        )
        if match:
            name = match.group(1).strip()

    if re.search(r"ansässigen Unternehmers", name, flags=re.IGNORECASE):
        parts = re.split(
            r"\s+(?=ansässigen Unternehmers)",
            name,
            maxsplit=1,
            flags=re.IGNORECASE,
        )
        if len(parts) == 2 and re.search(r"\d\s*%", parts[0]):
            name = parts[0].strip()

    for marker in BLEED_MARKERS:
        name = re.sub(marker, "", name, flags=re.IGNORECASE)

    name = re.sub(
        r"\s+und Leistungen\s+und Leistungen",
        " und Leistungen",
        name,
        flags=re.IGNORECASE,
    )
    name = re.sub(
        r"\s+für Investitionen\s+für\b",
        " für Investitionen",
        name,
        flags=re.IGNORECASE,
    )
    name = re.sub(
        r"\s+(und|oder|für|mit|nach|aus|an|auf|in|der|die|das|zum|zur|ohne|gegenüber|nach|vom|zur)$",
        "",
        name,
        flags=re.IGNORECASE,
    )

    return re.sub(r"\s+", " ", name).strip(" -")


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
    return repair_account_name(name.strip())


def should_continue_name(current_name: str, line: str) -> bool:
    if not line or is_noise(line):
        return False
    if ACCOUNT_RE.match(line):
        return False
    if re.search(r"\b\d{4}\b", line):
        return False
    if len(line) > 70:
        return False

    stripped = current_name.rstrip()
    if stripped.endswith(",") or stripped.endswith("-,"):
        return True
    if re.match(r"^und Leistungen\b", line, flags=re.IGNORECASE) and re.search(
        r"aus Lieferungen\s*$", current_name, flags=re.IGNORECASE
    ):
        return True
    if re.match(r"^(und|sowie)\s+\d", line, flags=re.IGNORECASE) and re.search(
        r"Vorsteuer\s*$", current_name, flags=re.IGNORECASE
    ):
        return True
    if re.match(r"^(für|ür)\b", line, flags=re.IGNORECASE) and re.search(
        r"Gegenständen\s*$", current_name, flags=re.IGNORECASE
    ):
        return True
    if re.match(r"^(Vorsteuer|Umsatzsteuer)\b", line, flags=re.IGNORECASE) and re.search(
        r"\b(ohne|mit|nach|für|ansässigen Unternehmers)$",
        current_name,
        flags=re.IGNORECASE,
    ):
        return True
    if len(line) <= 3 and line and line[0].islower():
        return True

    if len(current_name.strip()) <= 2:
        return True
    if name_looks_complete(current_name):
        return False
    if NEW_ACCOUNT_LINE.match(line) and not current_name.rstrip().endswith("-"):
        return False

    if current_name.endswith("-"):
        return True

    if not ends_incomplete(current_name):
        return False

    if line[0].islower():
        return True

    # Kurze Fortsetzung wie „Bauaufträge“ nach „…befindliche“
    if len(line.split()) <= 4 and not NEW_ACCOUNT_LINE.match(line):
        return True

    return False


def join_continuation(current_name: str, line: str) -> str:
    line = line.strip()
    current = current_name.rstrip()
    if current.endswith(","):
        current = current[:-1].rstrip()
    if current.endswith("-"):
        if re.match(r"^[A-Za-zÄÖÜäöüß]+-", line):
            return f"{current}, {line}"
        return current[:-1] + line
    return f"{current} {line}"


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
            "parser": "2026-07-06-v3",
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
