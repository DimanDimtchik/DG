#!/usr/bin/env python3
"""Erzeugt OT-Demo-Fixtures: 5 MA mit unterschiedlich vielen Überstunden je Monat.

Ausgabe:
  docs/testdata/import-fixtures/kontakte/kontakte-ot-variabel.csv
  docs/testdata/import-fixtures/stunden/stunden-ot-variabel.csv

Soll = 480 Min/Tag (CRM-Default). Pause = 30 Min.
Überstunden = Ist − Soll (nur wenn in Mitarbeiterdaten „Überstunden erlaubt“ = ja).
"""
from __future__ import annotations

import csv
from datetime import date, timedelta
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
OUT = ROOT / "docs" / "testdata" / "import-fixtures"
KONTAKTE = OUT / "kontakte"
STUNDEN = OUT / "stunden"

# Profil: Login, Vorname, Nachname, OT-Minuten pro Werktag je Monat (YYYY-MM)
PEOPLE = [
    {
        "login": "demo-ot-01",
        "first": "Mira",
        "last": "Keine",
        "ot_by_month": {"2026-06": 0, "2026-07": 0, "2026-08": 0, "2026-09": 0},
        "note": "kein Überstunden — Kontrollperson",
    },
    {
        "login": "demo-ot-02",
        "first": "Noah",
        "last": "Wenig",
        "ot_by_month": {"2026-06": 15, "2026-07": 30, "2026-08": 45, "2026-09": 60},
        "note": "steigend leicht",
    },
    {
        "login": "demo-ot-03",
        "first": "Olga",
        "last": "Mittel",
        "ot_by_month": {"2026-06": 60, "2026-07": 15, "2026-08": 90, "2026-09": 30},
        "note": "wechselnd mittel",
    },
    {
        "login": "demo-ot-04",
        "first": "Paul",
        "last": "Hoch",
        "ot_by_month": {"2026-06": 90, "2026-07": 90, "2026-08": 30, "2026-09": 120},
        "note": "meist hoch",
    },
    {
        "login": "demo-ot-05",
        "first": "Rita",
        "last": "Escal",
        "ot_by_month": {"2026-06": 120, "2026-07": 60, "2026-08": 120, "2026-09": 15},
        "note": "starke Monatsschwankung",
    },
]

START_HM = (8, 0)
PAUSE = 30
SOLL = 480
RANGE_START = date(2026, 6, 1)
RANGE_END = date(2026, 9, 19)


def weekdays(start: date, end: date) -> list[date]:
    out: list[date] = []
    d = start
    while d <= end:
        if d.weekday() < 5:
            out.append(d)
        d += timedelta(days=1)
    return out


def minutes_to_hm(total: int) -> str:
    return f"{total // 60}:{total % 60:02d}"


def end_clock(ot_minutes: int) -> str:
    """Beginn 08:00 + Soll + Pause + OT → Ende."""
    gross = SOLL + PAUSE + max(0, ot_minutes)
    end_min = START_HM[0] * 60 + START_HM[1] + gross
    h, m = divmod(end_min, 60)
    return f"{h:02d}:{m:02d}"


def write_csv(path: Path, headers: list[str], rows: list[list[str]]) -> None:
    path.parent.mkdir(parents=True, exist_ok=True)
    with path.open("w", encoding="utf-8-sig", newline="") as fh:
        w = csv.writer(fh, delimiter=";", quoting=csv.QUOTE_MINIMAL)
        w.writerow(headers)
        w.writerows(rows)


def main() -> None:
    days = weekdays(RANGE_START, RANGE_END)
    contact_rows: list[list[str]] = []
    hour_rows: list[list[str]] = []
    month_totals: dict[str, dict[str, int]] = {}

    for i, p in enumerate(PEOPLE):
        email = f"{p['first'].lower()}.{p['last'].lower()}@demo-import.ganz-om.invalid"
        phone = f"030 {1100 + i:04d} {2100 + i:04d}"
        contact_rows.append(
            [
                "Frau" if i % 2 == 0 else "Herr",
                p["first"],
                p["last"],
                "",
                email,
                phone,
                p["login"],
                "",
                "mitarbeiter",
            ]
        )
        month_totals[p["login"]] = {}
        for day in days:
            ym = day.strftime("%Y-%m")
            ot = int(p["ot_by_month"].get(ym, 0))
            month_totals[p["login"]][ym] = month_totals[p["login"]].get(ym, 0) + ot
            hour_rows.append(
                [
                    email,
                    p["login"],
                    f"{p['first']} {p['last']}",
                    day.strftime("%d.%m.%Y"),
                    "08:00",
                    end_clock(ot),
                    str(PAUSE),
                    "",
                ]
            )

    write_csv(
        KONTAKTE / "kontakte-ot-variabel.csv",
        ["Anrede", "Vorname", "Nachname", "Firma", "E-Mail", "Telefon", "Login", "Kundennummer", "Rolle"],
        contact_rows,
    )
    write_csv(
        STUNDEN / "stunden-ot-variabel.csv",
        ["E-Mail", "Login", "Name", "Datum", "Beginn", "Ende", "Pause_Minuten", "Stunden"],
        hour_rows,
    )

    print(f"OK: {KONTAKTE / 'kontakte-ot-variabel.csv'}")
    print(f"OK: {STUNDEN / 'stunden-ot-variabel.csv'}")
    print(f"Werktage {RANGE_START}–{RANGE_END}: {len(days)}")
    print("Erwartete Überstunden (Min / H:MM) bei Soll 480 und overtime_allowed=1:")
    months = ["2026-06", "2026-07", "2026-08", "2026-09"]
    print(f"{'Login':<12} " + " ".join(f"{m:>12}" for m in months) + f" {'Summe':>12}")
    for p in PEOPLE:
        login = p["login"]
        cells = []
        total = 0
        for m in months:
            mins = month_totals[login].get(m, 0)
            total += mins
            cells.append(f"{mins} ({minutes_to_hm(mins)})")
        print(f"{login:<12} " + " ".join(f"{c:>12}" for c in cells) + f" {total} ({minutes_to_hm(total)})")


if __name__ == "__main__":
    main()
