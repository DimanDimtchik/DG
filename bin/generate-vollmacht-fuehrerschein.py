#!/usr/bin/env python3
"""Erzeugt eine druckfertige Vollmacht zur Führerscheinabholung."""

from __future__ import annotations

import argparse
from pathlib import Path

from fpdf import FPDF


class VollmachtPdf(FPDF):
    def footer(self) -> None:
        self.set_y(-15)
        self.set_font("DejaVu", "", 9)
        self.set_text_color(120, 120, 120)
        self.cell(0, 10, f"Seite {self.page_no()}", align="C")


def build_pdf(
    *,
    vollmachtgeber_name: str,
    vollmachtgeber_strasse: str,
    vollmachtgeber_plz_ort: str,
    bevollmaechtigter_name: str,
    bevollmaechtigter_strasse: str,
    bevollmaechtigter_plz_ort: str,
    behoerde: str,
    ort_datum: str,
    output: Path,
) -> None:
    pdf = VollmachtPdf()
    pdf.set_auto_page_break(auto=True, margin=20)
    pdf.add_page()

    font_dir = Path("/usr/share/fonts/truetype/dejavu")
    pdf.add_font("DejaVu", "", str(font_dir / "DejaVuSans.ttf"))
    pdf.add_font("DejaVu", "B", str(font_dir / "DejaVuSans-Bold.ttf"))

    pdf.set_font("DejaVu", "B", 18)
    pdf.cell(0, 12, "VOLLMACHT", ln=True, align="C")
    pdf.ln(8)

    pdf.set_font("DejaVu", "", 11)
    pdf.multi_cell(
        0,
        6,
        "zur Abholung eines Führerscheins",
        align="C",
    )
    pdf.ln(10)

    pdf.set_font("DejaVu", "B", 11)
    pdf.cell(0, 7, "Vollmachtgeber (Inhaber des Führerscheins)", ln=True)
    pdf.set_font("DejaVu", "", 11)
    pdf.multi_cell(
        0,
        6,
        f"{vollmachtgeber_name}\n{vollmachtgeber_strasse}\n{vollmachtgeber_plz_ort}",
    )
    pdf.ln(6)

    pdf.set_font("DejaVu", "B", 11)
    pdf.cell(0, 7, "Bevollmächtigter", ln=True)
    pdf.set_font("DejaVu", "", 11)
    pdf.multi_cell(
        0,
        6,
        f"{bevollmaechtigter_name}\n{bevollmaechtigter_strasse}\n{bevollmaechtigter_plz_ort}",
    )
    pdf.ln(8)

    pdf.set_font("DejaVu", "", 11)
    text = (
        f"Hiermit bevollmächtige ich, {vollmachtgeber_name}, "
        f"{bevollmaechtigter_name}, wohnhaft {bevollmaechtigter_strasse}, "
        f"{bevollmaechtigter_plz_ort}, ausdrücklich und unwiderruflich, "
        f"meinen Führerschein in meinem Namen bei der {behoerde} "
        "abzuholen und entgegenzunehmen."
    )
    pdf.multi_cell(0, 6, text)
    pdf.ln(4)

    pdf.multi_cell(
        0,
        6,
        "Der Bevollmächtigte ist berechtigt, alle hierfür erforderlichen "
        "Erklärungen abzugeben, Unterlagen entgegenzunehmen und "
        "Entscheidungen entgegenzunehmen.",
    )
    pdf.ln(4)

    pdf.multi_cell(
        0,
        6,
        "Diese Vollmacht gilt ausschließlich für die Abholung des "
        "Führerscheins. Eine Kopie des Personalausweises des "
        "Vollmachtgebers ist der Abholung beizufügen.",
    )
    pdf.ln(12)

    pdf.set_font("DejaVu", "", 11)
    pdf.cell(0, 7, f"Ort, Datum: {ort_datum}", ln=True)
    pdf.ln(18)

    pdf.cell(95, 7, "________________________________________", ln=False)
    pdf.cell(0, 7, "________________________________________", ln=True)
    pdf.cell(95, 7, "Unterschrift Vollmachtgeber", ln=False)
    pdf.cell(0, 7, "Unterschrift Bevollmächtigter (optional)", ln=True)

    output.parent.mkdir(parents=True, exist_ok=True)
    pdf.output(str(output))


def main() -> None:
    parser = argparse.ArgumentParser(description="Vollmacht Führerscheinabholung als PDF")
    parser.add_argument(
        "-o",
        "--output",
        type=Path,
        default=Path("/opt/cursor/artifacts/Vollmacht-Fuehrerschein-Ganz.pdf"),
    )
    args = parser.parse_args()

    build_pdf(
        vollmachtgeber_name="Dietrich Ganz",
        vollmachtgeber_strasse="Auf dem Bühl 1",
        vollmachtgeber_plz_ort="87437 Kempten",
        bevollmaechtigter_name="Alexander Ganz",
        bevollmaechtigter_strasse="Schwalbenweg 76",
        bevollmaechtigter_plz_ort="87439 Kempten",
        behoerde="Führerscheinstelle Kempten",
        ort_datum="Kempten, 08.09.2026",
        output=args.output,
    )
    print(args.output)


if __name__ == "__main__":
    main()
