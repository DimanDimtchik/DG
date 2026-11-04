#!/usr/bin/env python3
"""Create Desktop PDF with clickable server price-check links."""
from __future__ import annotations

import os
from ctypes import create_unicode_buffer, windll
from pathlib import Path

from reportlab.lib.colors import HexColor
from reportlab.lib.pagesizes import A4
from reportlab.lib.styles import ParagraphStyle, getSampleStyleSheet
from reportlab.lib.units import mm
from reportlab.pdfbase import pdfmetrics
from reportlab.pdfbase.ttfonts import TTFont
from reportlab.platypus import Paragraph, SimpleDocTemplate

CSIDL_DESKTOP = 0


def desktop_path() -> Path:
    buf = create_unicode_buffer(260)
    windll.shell32.SHGetFolderPathW(None, CSIDL_DESKTOP, None, 0, buf)
    return Path(buf.value)


def register_fonts() -> tuple[str, str]:
    candidates = [
        (r"C:\Windows\Fonts\arial.ttf", r"C:\Windows\Fonts\arialbd.ttf"),
        (r"C:\Windows\Fonts\calibri.ttf", r"C:\Windows\Fonts\calibrib.ttf"),
        (r"C:\Windows\Fonts\segoeui.ttf", r"C:\Windows\Fonts\segoeuib.ttf"),
    ]
    for regular, bold in candidates:
        if Path(regular).exists():
            pdfmetrics.registerFont(TTFont("AppFont", regular))
            if Path(bold).exists():
                pdfmetrics.registerFont(TTFont("AppFontBold", bold))
                return "AppFont", "AppFontBold"
            return "AppFont", "AppFont"
    return "Helvetica", "Helvetica-Bold"


def link(url: str, label: str | None = None) -> str:
    text = label or url
    return f'<link href="{url}" color="#0b57d0"><u>{text}</u></link>'


def main() -> None:
    out = desktop_path() / "DG-Server-Preise-taeglich-pruefen.pdf"
    font_name, bold_name = register_fonts()

    doc = SimpleDocTemplate(
        str(out),
        pagesize=A4,
        leftMargin=18 * mm,
        rightMargin=18 * mm,
        topMargin=16 * mm,
        bottomMargin=16 * mm,
        title="DG CRM — Server taeglich pruefen",
        author="DG CRM",
    )

    styles = getSampleStyleSheet()
    title = ParagraphStyle(
        "TitleDE",
        parent=styles["Title"],
        fontName=bold_name,
        fontSize=16,
        leading=20,
        textColor=HexColor("#1a1a1a"),
        spaceAfter=6,
    )
    subtitle = ParagraphStyle(
        "SubDE",
        parent=styles["Normal"],
        fontName=font_name,
        fontSize=10,
        leading=13,
        textColor=HexColor("#444444"),
        spaceAfter=14,
    )
    h2 = ParagraphStyle(
        "H2DE",
        parent=styles["Heading2"],
        fontName=bold_name,
        fontSize=12,
        leading=15,
        textColor=HexColor("#0b3d2e"),
        spaceBefore=10,
        spaceAfter=6,
    )
    body = ParagraphStyle(
        "BodyDE",
        parent=styles["Normal"],
        fontName=font_name,
        fontSize=10,
        leading=13,
        textColor=HexColor("#222222"),
        spaceAfter=4,
    )
    link_style = ParagraphStyle(
        "LinkDE",
        parent=styles["Normal"],
        fontName=font_name,
        fontSize=10,
        leading=13,
        textColor=HexColor("#0b57d0"),
        spaceAfter=2,
    )
    small = ParagraphStyle(
        "SmallDE",
        parent=styles["Normal"],
        fontName=font_name,
        fontSize=8.5,
        leading=11,
        textColor=HexColor("#555555"),
        spaceBefore=8,
    )
    note = ParagraphStyle(
        "NoteDE",
        parent=styles["Normal"],
        fontName=font_name,
        fontSize=9,
        leading=12,
        textColor=HexColor("#333333"),
        spaceBefore=8,
        spaceAfter=8,
    )

    story = [
        Paragraph("DG CRM — Server täglich prüfen", title),
        Paragraph(
            "Anklickbare Links · OVH Rise-2 (Favorit) · Hetzner Serverbörse · Hetzner SX65-3<br/>"
            "Datei auf dem Desktop speichern und täglich öffnen. Stand der Links: 2026-10-31.",
            subtitle,
        ),
        Paragraph("1) OVHcloud Rise-2 (Deutschland / Limburg) — Favorit", h2),
        Paragraph(
            "Config-Ziel: 128 GB ECC, 2×960 GB NVMe Soft-RAID, Ubuntu LTS, "
            "ohne Windows/Plesk/cPanel/SQL Server.<br/>"
            "Grober Monatspreis Config: ~141 € zzgl. MwSt. · "
            "Tipp: 12 Monate → oft 5 % + Setup gratis.",
            body,
        ),
        Paragraph(link("https://eco.ovhcloud.com/de/rise/", "→ Rise-Übersicht (DE) öffnen"), link_style),
        Paragraph(
            link(
                "https://www.ovhcloud.com/de/bare-metal/prices/?range=rise",
                "→ Bare-Metal Preisliste Rise",
            ),
            link_style,
        ),
        Paragraph(
            link("https://www.ovhcloud.com/de/deals/", "→ OVH Deals / Aktionen (Black Friday beobachten)"),
            link_style,
        ),
        Paragraph(
            link("https://www.ovhcloud.com/de/black-friday/", "→ Black Friday Seite (ab ~Ende Nov prüfen)"),
            link_style,
        ),
        Paragraph("2) Hetzner Serverbörse (Auction)", h2),
        Paragraph(
            "Gebrauchte Dedicated Server — oft günstiger, Specs wechseln täglich. "
            "Nach ≥128 GB RAM + NVMe filtern.",
            body,
        ),
        Paragraph(link("https://www.hetzner.com/sb", "→ Hetzner Serverbörse / Server Auction"), link_style),
        Paragraph(
            link("https://robot.hetzner.com/order/market", "→ Robot Market (eingeloggt bestellen)"),
            link_style,
        ),
        Paragraph("3) Hetzner SX65-3 (Dedicated Storage)", h2),
        Paragraph(
            "Hinweis: Gemeint ist <b>SX65-3</b> (nicht Cloud CX). Specs: Ryzen 7 3700X, "
            "128 GB ECC, 2×1 TB NVMe + 4×22 TB HDD · ca. 292 €/Monat zzgl. MwSt.<br/>"
            "Viel HDD-Ballast für den Start — nur vergleichen, Favorit bleibt Rise-2.",
            body,
        ),
        Paragraph(link("https://www.hetzner.com/dedicated-rootserver/sx65/", "→ SX65 Produktseite"), link_style),
        Paragraph(
            link(
                "https://www.hetzner.com/dedicated-rootserver/sx65/configurator/",
                "→ SX65 Konfigurator (SX65-1 / -2 / -3 wählen)",
            ),
            link_style,
        ),
        Paragraph(
            link(
                "https://docs.hetzner.com/general/infrastructure-and-availability/order-processing/",
                "→ Hetzner Lieferzeiten / Verfügbarkeit (SX65-2 etc.)",
            ),
            link_style,
        ),
        Paragraph("Tägliche Kurz-Checkliste", h2),
        Paragraph(
            "1. OVH Rise: Preis, Setup, 12-Monats-Rabatt, Black-Friday-Banner?<br/>"
            "2. Hetzner Börse: Server mit ≥128 GB RAM und 1–2 TB NVMe?<br/>"
            "3. SX65-3: noch nötig? Preis/Verfügbarkeit nur zum Vergleich.<br/>"
            "Ziel: EU/DE · 128 GB · wenig SSD · CRM + lokale KI (Ollama).",
            body,
        ),
        Paragraph(
            "<b>Erinnerung:</b> Black Friday 2026 ≈ 27.11. — bis dahin OVH Deals beobachten. "
            "Weihnachten allein historisch schwächer als BF/Cyber Week.",
            note,
        ),
        Paragraph(
            "Nur interne Merkhilfe · keine Bestellpflicht · Preise ändern sich. "
            f"PDF: {out.name}",
            small,
        ),
    ]

    doc.build(story)
    print(f"WROTE {out}")
    print(f"SIZE {out.stat().st_size}")


if __name__ == "__main__":
    main()
