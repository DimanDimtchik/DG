#!/usr/bin/env python3
"""Erzeugt docs/testdata/import-fixtures/ (Kontakte + Stunden). Spiegel von bin/generate-import-fixtures.php."""
from __future__ import annotations

import csv
import json
import zipfile
from datetime import date, timedelta
from pathlib import Path
from xml.etree.ElementTree import Element, SubElement, ElementTree

ROOT = Path(__file__).resolve().parents[1]
OUT = ROOT / "docs" / "testdata" / "import-fixtures"
KONTAKTE = OUT / "kontakte"
STUNDEN = OUT / "stunden"


def roster() -> list[dict]:
    people = [
        ("demo-dup-01", "Anna", "Duplikat", True),
        ("demo-dup-02", "Bernd", "Doppel", True),
        ("demo-ma-03", "Clara", "Fischer", False),
        ("demo-ma-04", "David", "Hoffmann", False),
        ("demo-ma-05", "Elena", "Jung", False),
        ("demo-ma-06", "Felix", "Keller", False),
        ("demo-ma-07", "Greta", "Lange", False),
        ("demo-ma-08", "Hans", "Meier", False),
        ("demo-ma-09", "Ina", "Neumann", False),
        ("demo-ma-10", "Jonas", "Otto", False),
        ("demo-ma-11", "Karla", "Peters", False),
        ("demo-ma-12", "Leon", "Richter", False),
    ]
    out = []
    for i, (login, first, last, dup) in enumerate(people):
        out.append(
            {
                "login": login,
                "first": first,
                "last": last,
                "email": f"{first.lower()}.{last.lower()}@demo-import.ganz-om.invalid",
                "phone": f"030 {1000 + i:04d} {2000 + i:04d}",
                "dup": dup,
            }
        )
    return out


def write_csv(path: Path, headers: list[str], rows: list[list[str]]) -> None:
    path.parent.mkdir(parents=True, exist_ok=True)
    with path.open("w", encoding="utf-8-sig", newline="") as fh:
        w = csv.writer(fh, delimiter=";", quoting=csv.QUOTE_MINIMAL)
        w.writerow(headers)
        w.writerows(rows)


def write_json(path: Path, headers: list[str], rows: list[list[str]]) -> None:
    data = {"rows": [{h: (row[i] if i < len(row) else "") for i, h in enumerate(headers)} for row in rows]}
    path.write_text(json.dumps(data, ensure_ascii=False, indent=2) + "\n", encoding="utf-8")


def write_xml(path: Path, headers: list[str], rows: list[list[str]]) -> None:
    root = Element("records")
    for row in rows:
        el = SubElement(root, "row")
        for i, h in enumerate(headers):
            key = "".join(c if c.isalnum() or c == "_" else "_" for c in h) or "field"
            SubElement(el, key).text = row[i] if i < len(row) else ""
    tree = ElementTree(root)
    tree.write(path, encoding="utf-8", xml_declaration=True)


def col_name(idx: int) -> str:
    n = idx
    s = ""
    while True:
        s = chr(65 + (n % 26)) + s
        n = n // 26 - 1
        if n < 0:
            break
    return s


def write_xlsx(path: Path, headers: list[str], rows: list[list[str]]) -> None:
    all_rows = [headers] + rows
    shared: list[str] = []
    index: dict[str, int] = {}

    def si(v: str) -> int:
        if v not in index:
            index[v] = len(shared)
            shared.append(v)
        return index[v]

    sheet_rows = []
    for r_idx, line in enumerate(all_rows):
        row_num = r_idx + 1
        cells = []
        for c_idx, val in enumerate(line):
            ref = f"{col_name(c_idx)}{row_num}"
            cells.append(f'<c r="{ref}" t="s"><v>{si(str(val))}</v></c>')
        sheet_rows.append(f'<row r="{row_num}">{"".join(cells)}</row>')

    def esc(s: str) -> str:
        return (
            s.replace("&", "&amp;")
            .replace("<", "&lt;")
            .replace(">", "&gt;")
            .replace('"', "&quot;")
        )

    sst = (
        f'<sst xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" '
        f'count="{len(shared)}" uniqueCount="{len(shared)}">'
        + "".join(f"<si><t>{esc(s)}</t></si>" for s in shared)
        + "</sst>"
    )
    sheet = (
        '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
        f'<sheetData>{"".join(sheet_rows)}</sheetData></worksheet>'
    )
    workbook = (
        '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" '
        'xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
        '<sheets><sheet name="Import" sheetId="1" r:id="rId1"/></sheets></workbook>'
    )
    rels = (
        '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
        '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" '
        'Target="xl/workbook.xml"/></Relationships>'
    )
    wb_rels = (
        '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
        '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" '
        'Target="worksheets/sheet1.xml"/>'
        '<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/sharedStrings" '
        'Target="sharedStrings.xml"/></Relationships>'
    )
    content_types = (
        '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
        '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
        '<Default Extension="xml" ContentType="application/xml"/>'
        '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
        '<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
        '<Override PartName="/xl/sharedStrings.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sharedStrings+xml"/>'
        "</Types>"
    )

    if path.exists():
        path.unlink()
    with zipfile.ZipFile(path, "w", compression=zipfile.ZIP_DEFLATED) as z:
        z.writestr("[Content_Types].xml", content_types)
        z.writestr("_rels/.rels", rels)
        z.writestr("xl/workbook.xml", workbook)
        z.writestr("xl/_rels/workbook.xml.rels", wb_rels)
        z.writestr("xl/worksheets/sheet1.xml", sheet)
        z.writestr("xl/sharedStrings.xml", sst)


def contact_rows(source: str, people: list[dict]) -> tuple[list[str], list[list[str]]]:
    if source in ("excel", "other"):
        h = ["Anrede", "Vorname", "Nachname", "E-Mail", "Telefon", "Login", "Rolle"]
        r = [["Frau/Herr", p["first"], p["last"], p["email"], p["phone"], p["login"], "mitarbeiter"] for p in people]
        return h, r
    if source == "outlook":
        h = ["Given Name", "Family Name", "E-mail Address", "Business Phone", "Login", "Rolle"]
        r = [[p["first"], p["last"], p["email"], p["phone"], p["login"], "mitarbeiter"] for p in people]
        return h, r
    if source == "google":
        h = ["Given Name", "Family Name", "E-Mail", "Phone 1 - Value", "Login", "Rolle"]
        r = [[p["first"], p["last"], p["email"], p["phone"], p["login"], "mitarbeiter"] for p in people]
        return h, r
    if source == "datev":
        h = ["Vorname", "Nachname", "E-Mail", "Telefon", "Personalnummer", "Rolle"]
        r = [[p["first"], p["last"], p["email"], p["phone"], p["login"], "mitarbeiter"] for p in people]
        return h, r
    if source == "lexware":
        h = ["Vorname", "Nachname", "E-Mail", "Telefon", "Benutzername", "Rolle"]
        r = [[p["first"], p["last"], p["email"], p["phone"], p["login"], "mitarbeiter"] for p in people]
        return h, r
    if source == "sevdesk":
        h = ["Vorname", "Nachname", "email", "Telefon", "Login", "Rolle"]
        r = [[p["first"], p["last"], p["email"], p["phone"], p["login"], "mitarbeiter"] for p in people]
        return h, r
    if source == "shiftbase":
        h = ["Employee", "E-Mail", "Phone", "Personalnummer", "Rolle"]
        r = [[f"{p['first']} {p['last']}", p["email"], p["phone"], p["login"], "mitarbeiter"] for p in people]
        return h, r
    raise ValueError(source)


def weekdays(start: date, end: date) -> list[date]:
    out = []
    d = start
    while d <= end:
        if d.weekday() < 5:
            out.append(d)
        d += timedelta(days=1)
    return out


def main() -> None:
    KONTAKTE.mkdir(parents=True, exist_ok=True)
    STUNDEN.mkdir(parents=True, exist_ok=True)
    people = roster()
    dups = [p for p in people if p["dup"]]
    sources = ["excel", "outlook", "google", "datev", "lexware", "sevdesk", "shiftbase", "other"]

    h, r = contact_rows("excel", dups)
    write_csv(KONTAKTE / "00-seed-duplikate.csv", h, r)

    for src in sources:
        h, r = contact_rows(src, people)
        write_csv(KONTAKTE / f"kontakte-{src}.csv", h, r)

    h, r = contact_rows("excel", people)
    write_csv(KONTAKTE / "kontakte-excel.txt", h, r)
    write_json(KONTAKTE / "kontakte-excel.json", h, r)
    write_xml(KONTAKTE / "kontakte-excel.xml", h, r)
    write_xlsx(KONTAKTE / "kontakte-excel.xlsx", h, r)

    days = weekdays(date(2026, 6, 1), date(2026, 9, 19))
    excel_rows: list[list[str]] = []
    shift_rows: list[list[str]] = []
    crew_rows: list[list[str]] = []
    other_rows: list[list[str]] = []

    for pi, p in enumerate(people):
        for di, day in enumerate(days):
            start = "08:00" if (pi + di) % 2 == 0 else "07:30"
            end = "17:00" if (pi + di) % 3 == 0 else "16:30"
            pause = "30"
            de = day.strftime("%d.%m.%Y")
            iso = day.isoformat()
            name = f"{p['first']} {p['last']}"
            excel_rows.append([p["email"], p["login"], name, de, start, end, pause, ""])
            shift_rows.append([name, p["email"], iso, start, end, pause, ""])
            crew_rows.append([name, p["login"], de, start, end, pause])
            if (pi + di) % 4 == 0:
                other_rows.append([p["login"], de, "", "", "", "8"])
            else:
                other_rows.append([p["login"], de, start, end, pause, ""])

    write_csv(
        STUNDEN / "stunden-excel.csv",
        ["E-Mail", "Login", "Name", "Datum", "Beginn", "Ende", "Pause_Minuten", "Stunden"],
        excel_rows,
    )
    write_xlsx(
        STUNDEN / "stunden-excel.xlsx",
        ["E-Mail", "Login", "Name", "Datum", "Beginn", "Ende", "Pause_Minuten", "Stunden"],
        excel_rows,
    )
    write_csv(
        STUNDEN / "stunden-shiftbase.csv",
        ["Employee", "Email", "Date", "Clocked in", "Clocked out", "Break", "Hours"],
        shift_rows,
    )
    write_csv(
        STUNDEN / "stunden-crewmeister.csv",
        ["Mitarbeiter", "Personalnummer", "Datum", "Kommen", "Gehen", "Pause"],
        crew_rows,
    )
    write_csv(
        STUNDEN / "stunden-other.csv",
        ["Login", "Datum", "Beginn", "Ende", "Pause_Minuten", "Stunden"],
        other_rows,
    )

    print(f"OK: {OUT}")
    print(f"  Roster: {len(people)} (Seed-Duplikate: {len(dups)})")
    print(f"  Werktage: {len(days)} -> Stundenzeilen: {len(days) * len(people)}")


if __name__ == "__main__":
    main()
