#!/usr/bin/env python3
"""Erzeugt Dashboard-Überblick-Video (MP4) + WebVTT-Untertitel für die Akademie."""

from __future__ import annotations

import asyncio
import json
import re
import subprocess
import sys
import tempfile
from dataclasses import dataclass
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
OUT_DIR = ROOT / "storage/media/training/allgemein"
VOICE = "de-DE-KatjaNeural"
FONT = "/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf"
EDGE_TTS = Path.home() / ".local/bin/edge-tts"


@dataclass
class Segment:
    title: str
    text: str
    subtitle: str | None = None


SEGMENTS: list[Segment] = [
    Segment(
        "Dashboard",
        "Willkommen zur kurzen Einführung ins Dashboard. Nach dem Login sehen Sie hier alle Module als Kacheln — Ihr Einstieg in das CRM. Links finden Sie dieselben Bereiche im Menü. In diesem Video erklären wir nur, wofür jede Kachel gedacht ist. Was Sie nach dem Öffnen sehen, behandeln wir in eigenen Videos.",
    ),
    Segment(
        "Kontakte",
        "Kontakte: Hier werden alle Kontakte Ihres Unternehmens gespeichert — Kunden, Lieferanten, Mitarbeiter und Behörden. Alle für Unternehmen relevanten Kontaktfelder sind voreingestellt, inklusive eines Feldes für Bemerkungen.",
    ),
    Segment(
        "Terminkalender",
        "Terminkalender: Termine, Buchungen und Kalenderbereiche im Überblick — für Planung und Abstimmung im Team.",
    ),
    Segment(
        "Zeiterfassung",
        "Zeiterfassung: Einstempeln, Pausen und Arbeitszeiten erfassen — Grundlage für HR und Auswertungen.",
    ),
    Segment(
        "Post",
        "Post: Ihre Postfächer — Eingang lesen und Nachrichten direkt aus dem CRM versenden.",
    ),
    Segment(
        "Akademie",
        "Akademie: Schulungen, Erklärvideos und Zertifikate — hier finden Sie Anleitungen zu den Modulen.",
    ),
    Segment(
        "Artikel & Leistungen",
        "Artikel und Leistungen: Der Katalog für verkaufte und eingekaufte Artikel sowie Leistungen — Basis für Angebote, Belege und Lager.",
    ),
    Segment(
        "Lager",
        "Lager: Bestände, Lagerorte und Bewegungen — von Wareneingang bis Inventur.",
    ),
    Segment(
        "Bilder",
        "Bilder: Medienbibliothek für Logos, Fotos und andere Bilddateien — nur für Administratoren.",
    ),
    Segment(
        "Konten",
        "Konten: Kontenrahmen und Kontenhinweise — Ihr Nachschlagewerk für die Buchführung.",
    ),
    Segment(
        "Belege",
        "Belege: Eingangs- und Ausgangsbelege erfassen — mit Steuerfeldern und Kontenzuordnung.",
    ),
    Segment(
        "Überweisungen",
        "Überweisungen: Zahlungen vorbereiten — inklusive QR-Code und Fotovorlage für den Bankauftrag.",
    ),
    Segment(
        "Kontenübersicht",
        "Kontenübersicht: Salden und Kontoauszüge je Geschäftsjahr auf einen Blick.",
    ),
    Segment(
        "Offene Posten",
        "Offene Posten: Offene Forderungen und Verbindlichkeiten — wer schuldet wem noch etwas.",
    ),
    Segment(
        "Kassenbuch",
        "Kassenbuch: Bar-Ein- und Ausgänge aus Kassenbelegen dokumentieren.",
    ),
    Segment(
        "Manuelle Buchungen",
        "Manuelle Buchungen: Journalbuchungen ohne Beleg — wenn Soll und Haben direkt gebucht werden.",
    ),
    Segment(
        "Bilanz & GuV",
        "Bilanz und GuV: Auswertungen zu Vermögen, Schulden und Ergebnis je Geschäftsjahr.",
    ),
    Segment(
        "BWA",
        "BWA: Betriebswirtschaftliche Auswertung — der klassische Monatsüberblick fürs Management.",
    ),
    Segment(
        "SuSa",
        "SuSa: Summen- und Saldenliste — Kontenstände kompakt für Prüfung und Steuerberater.",
    ),
    Segment(
        "Bankabgleich",
        "Bankabgleich: Kontoauszüge importieren und Belegen zuordnen.",
    ),
    Segment(
        "Steuerberater-Export",
        "Steuerberater-Export: Daten für DATEV, Agenda oder Addison — Buchungsstapel und Belege.",
    ),
    Segment(
        "UStVA",
        "UStVA: Umsatzsteuer-Voranmeldung vorbereiten — inklusive ELSTER-Export.",
    ),
    Segment(
        "Jahresabschluss",
        "Jahresabschluss: Checkliste und Assistent für GuV-Abschluss und Saldenvortrag.",
    ),
    Segment(
        "Seiten",
        "Seiten: Inhaltsseiten der öffentlichen Website anlegen und pflegen.",
    ),
    Segment(
        "Formulare",
        "Formulare: Kontakt- und Anfrageformulare bauen und Eingänge empfangen.",
    ),
    Segment(
        "Statistik",
        "Statistik: Seitenaufrufe im CRM und Links zu Google Analytics oder Tag Manager.",
    ),
    Segment(
        "Menü",
        "Menü: Navigation der Website — welche Seiten wo verlinkt sind.",
    ),
    Segment(
        "Kopf & Fuß",
        "Kopf und Fuß: Kopfzeile, Fußzeile und zusätzliche Skripte der Website.",
    ),
    Segment(
        "Design",
        "Design: Farben und Erscheinungsbild der öffentlichen Website.",
    ),
    Segment(
        "Einstellungen",
        "Einstellungen: Firma, E-Mail, Abteilungen, Module und System — nur für berechtigte Nutzer. Das war der Kurzüberblick über alle Dashboard-Kacheln. Wählen Sie eine Kachel, wenn Sie in einem Bereich arbeiten möchten — die Details erklären wir in den Modul-Videos der Akademie. Viel Erfolg!",
    ),
]


def run(cmd: list[str]) -> None:
    subprocess.run(cmd, check=True)


def probe_duration(path: Path) -> float:
    out = subprocess.check_output(
        [
            "ffprobe",
            "-v",
            "error",
            "-show_entries",
            "format=duration",
            "-of",
            "default=noprint_wrappers=1:nokey=1",
            str(path),
        ],
        text=True,
    ).strip()
    return float(out)


def escape_drawtext(text: str) -> str:
    text = text.replace("\\", "\\\\")
    text = text.replace(":", "\\:")
    text = text.replace("'", "\\'")
    text = text.replace("%", "\\%")
    return text


def wrap_subtitle(text: str, width: int = 42) -> str:
    words = text.split()
    lines: list[str] = []
    line: list[str] = []
    for word in words:
        candidate = " ".join(line + [word])
        if len(candidate) > width and line:
            lines.append(" ".join(line))
            line = [word]
        else:
            line.append(word)
    if line:
        lines.append(" ".join(line))
    return "\\n".join(escape_drawtext(l) for l in lines[:4])


async def synthesize(text: str, mp3: Path) -> None:
    cmd = [
        str(EDGE_TTS),
        "--voice",
        VOICE,
        "--text",
        text,
        "--write-media",
        str(mp3),
    ]
    proc = await asyncio.create_subprocess_exec(*cmd)
    code = await proc.wait()
    if code != 0:
        raise RuntimeError(f"edge-tts failed for: {text[:60]}...")


def build_clip(title: str, subtitle: str, audio: Path, out: Path) -> float:
    duration = probe_duration(audio) + 0.4
    wrapped = wrap_subtitle(subtitle)
    title_esc = escape_drawtext(title)
    vf = (
        f"drawtext=fontfile={FONT}:text='Dashboard':fontsize=36:fontcolor=0x8899aa:x=80:y=80,"
        f"drawtext=fontfile={FONT}:text='{title_esc}':fontsize=64:fontcolor=white:x=80:y=160,"
        f"drawtext=fontfile={FONT}:text='{wrapped}':fontsize=34:fontcolor=0xe8eef5:x=80:y=280:line_spacing=12"
    )
    run(
        [
            "ffmpeg",
            "-y",
            "-f",
            "lavfi",
            "-i",
            f"color=c=0x15202b:s=1920x1080:d={duration:.3f}",
            "-i",
            str(audio),
            "-vf",
            vf,
            "-c:v",
            "libx264",
            "-pix_fmt",
            "yuv420p",
            "-c:a",
            "aac",
            "-b:a",
            "128k",
            "-shortest",
            str(out),
        ]
    )
    return duration


def format_ts(seconds: float) -> str:
    ms = int(round(seconds * 1000))
    h, rem = divmod(ms, 3_600_000)
    m, rem = divmod(rem, 60_000)
    s, ms = divmod(rem, 1000)
    return f"{h:02d}:{m:02d}:{s:02d}.{ms:03d}"


def write_vtt(entries: list[tuple[float, float, str]], path: Path) -> None:
    lines = ["WEBVTT", ""]
    for start, end, text in entries:
        lines.append(f"{format_ts(start)} --> {format_ts(end)}")
        lines.append(text.strip())
        lines.append("")
    path.write_text("\n".join(lines), encoding="utf-8")


async def main() -> int:
    if not EDGE_TTS.is_file():
        print("edge-tts nicht gefunden. pip install edge-tts", file=sys.stderr)
        return 1
    if not Path(FONT).is_file():
        print(f"Schrift nicht gefunden: {FONT}", file=sys.stderr)
        return 1

    OUT_DIR.mkdir(parents=True, exist_ok=True)
    manifest: list[dict] = []

    with tempfile.TemporaryDirectory(prefix="dg-academy-video-") as tmp:
        tmp_path = Path(tmp)
        clips: list[Path] = []
        vtt_entries: list[tuple[float, float, str]] = []
        cursor = 0.0

        for idx, seg in enumerate(SEGMENTS):
            mp3 = tmp_path / f"{idx:03d}.mp3"
            clip = tmp_path / f"{idx:03d}.mp4"
            print(f"[{idx + 1}/{len(SEGMENTS)}] {seg.title}")
            await synthesize(seg.text, mp3)
            sub = seg.subtitle or seg.text
            duration = build_clip(seg.title, sub, mp3, clip)
            clips.append(clip)
            vtt_entries.append((cursor, cursor + duration - 0.05, sub))
            cursor += duration
            manifest.append(
                {
                    "title": seg.title,
                    "text": seg.text,
                    "duration_sec": round(duration, 2),
                }
            )

        concat_list = tmp_path / "concat.txt"
        concat_list.write_text("\n".join(f"file '{c}'" for c in clips), encoding="utf-8")
        mp4_out = OUT_DIR / "dashboard-ueberblick.mp4"
        vtt_out = OUT_DIR / "dashboard-ueberblick.vtt"
        run(
            [
                "ffmpeg",
                "-y",
                "-f",
                "concat",
                "-safe",
                "0",
                "-i",
                str(concat_list),
                "-c",
                "copy",
                str(mp4_out),
            ]
        )
        write_vtt(vtt_entries, vtt_out)

    meta_out = OUT_DIR / "dashboard-ueberblick.meta.json"
    meta_out.write_text(
        json.dumps(
            {
                "title": "Dashboard — Kurzüberblick aller Kacheln",
                "voice": VOICE,
                "duration_sec": round(cursor, 1),
                "segments": manifest,
            },
            ensure_ascii=False,
            indent=2,
        ),
        encoding="utf-8",
    )

    print(f"\nFertig: {mp4_out}")
    print(f"Untertitel: {vtt_out}")
    print(f"Dauer: {cursor:.1f} s ({cursor / 60:.1f} min)")
    return 0


if __name__ == "__main__":
    raise SystemExit(asyncio.run(main()))
