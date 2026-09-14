#!/usr/bin/env python3
"""Dashboard-Schulungsvideo: echtes Kachel-Layout, Kamera-Schwenk/Zoom je Kachel, Stimme + VTT."""

from __future__ import annotations

import asyncio
import json
import subprocess
import sys
import tempfile
from dataclasses import dataclass
from pathlib import Path

from PIL import Image, ImageDraw, ImageFont

ROOT = Path(__file__).resolve().parents[1]
OUT_DIR = ROOT / "storage/media/training/allgemein"
VOICE = "de-DE-KatjaNeural"
FONT_BOLD = "/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf"
FONT_REG = "/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf"
EDGE_TTS = Path.home() / ".local/bin/edge-tts"

OUT_W, OUT_H = 1920, 1080
COLS = 4
MARGIN_X = 64
HEADER_H = 132
GAP = 14
CARD_H = 98
FPS = 25
TRANSITION_SEC = 0.85
FOCUS_ZOOM = 2.35

# CRM-Farben (dg.css Standard)
BG = (245, 243, 240)
PRIMARY = (110, 98, 88)
BRAND = (196, 181, 165)
TEXT = (45, 42, 38)
TEXT_MUTED = (95, 90, 84)
BORDER = (220, 214, 206)


@dataclass
class Tile:
    title: str
    description: str
    narration: str


TILES: list[Tile] = [
    Tile("Kontakte", "Benutzer, Kunden, Lieferanten und Mitarbeiter verwalten.",
         "Kontakte: Hier werden alle Kontakte Ihres Unternehmens gespeichert — Kunden, Lieferanten, Mitarbeiter und Behörden. Alle für Unternehmen relevanten Kontaktfelder sind voreingestellt, inklusive eines Feldes für Bemerkungen."),
    Tile("Terminkalender", "Buchungen, Artikel und Kalender im Blick behalten.",
         "Terminkalender: Termine, Buchungen und Kalenderbereiche im Überblick — für Planung und Abstimmung im Team."),
    Tile("Zeiterfassung", "Einstempeln, Pausen und Teamübersicht für HR.",
         "Zeiterfassung: Einstempeln, Pausen und Arbeitszeiten erfassen — Grundlage für HR und Auswertungen."),
    Tile("Post", "Postfächer, Eingang und Nachrichten versenden.",
         "Post: Ihre Postfächer — Eingang lesen und Nachrichten direkt aus dem CRM versenden."),
    Tile("Akademie", "Schulungen, Erklärvideos und Zertifikate.",
         "Akademie: Schulungen, Erklärvideos und Zertifikate — hier finden Sie Anleitungen zu den Modulen."),
    Tile("Artikel & Leistungen", "Artikel- und Leistungskatalog pflegen.",
         "Artikel und Leistungen: Der Katalog für verkaufte und eingekaufte Artikel sowie Leistungen — Basis für Angebote, Belege und Lager."),
    Tile("Lager", "Lagerbestände, Bewegungen aus Belegen und Inventur.",
         "Lager: Bestände, Lagerorte und Bewegungen — von Wareneingang bis Inventur."),
    Tile("Bilder", "Medien, Logos und Bilder verwalten.",
         "Bilder: Medienbibliothek für Logos, Fotos und andere Bilddateien — nur für Administratoren."),
    Tile("Konten", "Kontenrahmen durchsuchen und Kontenhinweise einsehen.",
         "Konten: Kontenrahmen und Kontenhinweise — Ihr Nachschlagewerk für die Buchführung."),
    Tile("Belege", "Belege erfassen mit Steuerfeldern und Kontenzuordnung.",
         "Belege: Eingangs- und Ausgangsbelege erfassen — mit Steuerfeldern und Kontenzuordnung."),
    Tile("Überweisungen", "Überweisungen mit QR-Code und Fotovorlage.",
         "Überweisungen: Zahlungen vorbereiten — inklusive QR-Code und Fotovorlage für den Bankauftrag."),
    Tile("Kontenübersicht", "Kontensalden und Kontoauszüge je Geschäftsjahr.",
         "Kontenübersicht: Salden und Kontoauszüge je Geschäftsjahr auf einen Blick."),
    Tile("Offene Posten", "Offene Forderungen und Verbindlichkeiten (OPOS).",
         "Offene Posten: Offene Forderungen und Verbindlichkeiten — wer schuldet wem noch etwas."),
    Tile("Kassenbuch", "Bar-Ein- und Ausgänge aus Kassenbelegen.",
         "Kassenbuch: Bar-Ein- und Ausgänge aus Kassenbelegen dokumentieren."),
    Tile("Manuelle Buchungen", "Freie Journalbuchungen ohne Beleg.",
         "Manuelle Buchungen: Journalbuchungen ohne Beleg — wenn Soll und Haben direkt gebucht werden."),
    Tile("Bilanz & GuV", "Bilanz und GuV je Geschäftsjahr.",
         "Bilanz und GuV: Auswertungen zu Vermögen, Schulden und Ergebnis je Geschäftsjahr."),
    Tile("BWA", "Betriebswirtschaftliche Auswertung.",
         "BWA: Betriebswirtschaftliche Auswertung — der klassische Monatsüberblick fürs Management."),
    Tile("SuSa", "Summen- und Saldenliste.",
         "SuSa: Summen- und Saldenliste — Kontenstände kompakt für Prüfung und Steuerberater."),
    Tile("Bankabgleich", "CAMT.053 importieren und Belege zuordnen.",
         "Bankabgleich: Kontoauszüge importieren und Belegen zuordnen."),
    Tile("Steuerberater-Export", "DATEV, Agenda, Addison — Buchungsstapel.",
         "Steuerberater-Export: Daten für DATEV, Agenda oder Addison — Buchungsstapel und Belege."),
    Tile("UStVA", "Umsatzsteuer-Voranmeldung und ELSTER-CSV.",
         "UStVA: Umsatzsteuer-Voranmeldung vorbereiten — inklusive ELSTER-Export."),
    Tile("Jahresabschluss", "Checkliste, GuV-Abschluss, Saldenvortrag.",
         "Jahresabschluss: Checkliste und Assistent für GuV-Abschluss und Saldenvortrag."),
    Tile("Seiten", "Seiten der öffentlichen Website anlegen.",
         "Seiten: Inhaltsseiten der öffentlichen Website anlegen und pflegen."),
    Tile("Formulare", "Formulare bauen und Eingänge empfangen.",
         "Formulare: Kontakt- und Anfrageformulare bauen und Eingänge empfangen."),
    Tile("Statistik", "Seitenaufrufe und Analytics-Links.",
         "Statistik: Seitenaufrufe im CRM und Links zu Google Analytics oder Tag Manager."),
    Tile("Menü", "Navigation der Website pflegen.",
         "Menü: Navigation der Website — welche Seiten wo verlinkt sind."),
    Tile("Kopf & Fuß", "Kopfzeile, Fußzeile und Skripte.",
         "Kopf und Fuß: Kopfzeile, Fußzeile und zusätzliche Skripte der Website."),
    Tile("Design", "Farben der öffentlichen Website.",
         "Design: Farben und Erscheinungsbild der öffentlichen Website."),
    Tile("Einstellungen", "Firma, E-Mail, Module und System.",
         "Einstellungen: Firma, E-Mail, Abteilungen, Module und System — nur für berechtigte Nutzer. Das war der Kurzüberblick über alle Dashboard-Kacheln. Wählen Sie eine Kachel, wenn Sie in einem Bereich arbeiten möchten — die Details erklären wir in den Modul-Videos der Akademie. Viel Erfolg!"),
]

INTRO = (
    "Willkommen zur kurzen Einführung ins Dashboard. Nach dem Login sehen Sie hier alle Module als Kacheln — "
    "Ihr Einstieg in das CRM. Links finden Sie dieselben Bereiche im Menü. "
    "In diesem Video erklären wir nur, wofür jede Kachel gedacht ist. "
    "Was Sie nach dem Öffnen sehen, behandeln wir in eigenen Videos."
)


def card_width() -> int:
    return (OUT_W - 2 * MARGIN_X - (COLS - 1) * GAP) // COLS


def canvas_size() -> tuple[int, int]:
    rows = (len(TILES) + COLS - 1) // COLS
    h = HEADER_H + rows * CARD_H + (rows - 1) * GAP + 48
    return OUT_W, h


def tile_rect(index: int) -> tuple[int, int, int, int]:
    cw = card_width()
    row = index // COLS
    col = index % COLS
    x = MARGIN_X + col * (cw + GAP)
    y = HEADER_H + row * (CARD_H + GAP)
    return x, y, cw, CARD_H


def tile_center(index: int) -> tuple[float, float]:
    x, y, w, h = tile_rect(index)
    return x + w / 2, y + h / 2


def ease_in_out(t: float) -> float:
    return t * t * (3 - 2 * t)


def load_fonts() -> tuple[ImageFont.FreeTypeFont, ImageFont.FreeTypeFont]:
    return (
        ImageFont.truetype(FONT_BOLD, 34),
        ImageFont.truetype(FONT_REG, 17),
    )


def wrap_text(draw: ImageDraw.ImageDraw, text: str, font: ImageFont.FreeTypeFont, max_w: int) -> list[str]:
    words = text.split()
    lines: list[str] = []
    line: list[str] = []
    for word in words:
        test = " ".join(line + [word])
        if draw.textlength(test, font=font) > max_w and line:
            lines.append(" ".join(line))
            line = [word]
        else:
            line.append(word)
    if line:
        lines.append(" ".join(line))
    return lines[:2]


def draw_card(
    draw: ImageDraw.ImageDraw,
    x: int,
    y: int,
    w: int,
    h: int,
    tile: Tile,
    fonts: tuple[ImageFont.FreeTypeFont, ImageFont.FreeTypeFont],
) -> None:
    title_font, desc_font = fonts
    fill, border, accent = (250, 248, 246), BORDER, BRAND
    draw.rectangle([x, y + 3, x + w, y + h], fill=fill, outline=border, width=1)
    draw.rectangle([x, y, x + w, y + 3], fill=accent)
    draw.rectangle([x + 12, y + 16, x + 44, y + 48], outline=border, width=1)
    draw.text((x + 20, y + 22), tile.title[:1], fill=PRIMARY, font=title_font)
    draw.text((x + 52, y + 20), tile.title, fill=TEXT, font=title_font)
    for i, line in enumerate(wrap_text(draw, tile.description, desc_font, w - 24)):
        draw.text((x + 12, y + 56 + i * 20), line, fill=TEXT_MUTED, font=desc_font)


def build_dashboard_canvas() -> Image.Image:
    cw, ch = canvas_size()
    fonts = load_fonts()
    title_font, lead_font = fonts
    img = Image.new("RGB", (cw, ch), BG)
    draw = ImageDraw.Draw(img)
    draw.text((MARGIN_X, 36), "Willkommen", fill=TEXT, font=title_font)
    draw.text(
        (MARGIN_X, 82),
        "Wählen Sie im Menü links ein Modul oder starten Sie direkt über eine Kachel.",
        fill=TEXT_MUTED,
        font=lead_font,
    )
    for i, tile in enumerate(TILES):
        x, y, w, h = tile_rect(i)
        draw_card(draw, x, y, w, h, tile, fonts)
    return img


def overview_scale(extra: float = 1.0) -> float:
    cw, ch = canvas_size()
    return min(OUT_W / cw, OUT_H / ch) * extra


def render_overview(base: Image.Image, extra_zoom: float = 1.0) -> Image.Image:
    cw, ch = base.size
    scale = overview_scale(extra_zoom)
    nw, nh = max(1, int(cw * scale)), max(1, int(ch * scale))
    scaled = base.resize((nw, nh), Image.Resampling.LANCZOS)
    frame = Image.new("RGB", (OUT_W, OUT_H), BG)
    frame.paste(scaled, ((OUT_W - nw) // 2, (OUT_H - nh) // 2))
    return frame


def render_viewport(
    base: Image.Image,
    center_x: float,
    center_y: float,
    zoom: float,
    focus_index: int | None = None,
) -> Image.Image:
    """Kamera: schwenkt und zoomt — keine dunkle Folie, Kacheln bleiben sichtbar."""
    cw, ch = canvas_size()

    view_w = OUT_W / zoom
    view_h = OUT_H / zoom
    left = max(0, min(center_x - view_w / 2, max(0, cw - view_w)))
    top = max(0, min(center_y - view_h / 2, max(0, ch - view_h)))
    right = min(cw, left + view_w)
    bottom = min(ch, top + view_h)
    crop = base.crop((int(left), int(top), int(right), int(bottom)))
    frame = crop.resize((OUT_W, OUT_H), Image.Resampling.LANCZOS)

    if focus_index is not None:
        x, y, w, h = tile_rect(focus_index)
        sx = (x - left) / max(right - left, 1) * OUT_W
        sy = (y - top) / max(bottom - top, 1) * OUT_H
        sw = w / max(right - left, 1) * OUT_W
        sh = h / max(bottom - top, 1) * OUT_H
        overlay = Image.new("RGBA", (OUT_W, OUT_H), (0, 0, 0, 0))
        od = ImageDraw.Draw(overlay)
        pad = 8
        od.rectangle(
            [sx - pad, sy - pad, sx + sw + pad, sy + sh + pad],
            outline=PRIMARY + (255,),
            width=5,
        )
        frame = Image.alpha_composite(frame.convert("RGBA"), overlay).convert("RGB")

        mask = Image.new("L", (OUT_W, OUT_H), 0)
        md = ImageDraw.Draw(mask)
        md.rectangle([0, 0, OUT_W, OUT_H], fill=28)
        md.rectangle([sx - 10, sy - 10, sx + sw + 10, sy + sh + 10], fill=0)
        dim = Image.new("RGBA", (OUT_W, OUT_H), (25, 23, 20, 255))
        frame = Image.composite(
            Image.alpha_composite(frame.convert("RGBA"), dim).convert("RGB"),
            frame,
            mask,
        )

    return frame


def lerp(a: float, b: float, t: float) -> float:
    return a + (b - a) * t


def run(cmd: list[str]) -> None:
    subprocess.run(cmd, check=True)


def probe_duration(path: Path) -> float:
    out = subprocess.check_output(
        [
            "ffprobe", "-v", "error",
            "-show_entries", "format=duration",
            "-of", "default=noprint_wrappers=1:nokey=1",
            str(path),
        ],
        text=True,
    ).strip()
    return float(out)


async def synthesize(text: str, mp3: Path) -> None:
    proc = await asyncio.create_subprocess_exec(
        str(EDGE_TTS), "--voice", VOICE, "--text", text, "--write-media", str(mp3)
    )
    if await proc.wait() != 0:
        raise RuntimeError("edge-tts failed")


def encode_clip(frames: list[Image.Image], audio: Path, out: Path) -> float:
    duration = probe_duration(audio)
    trans_frames = len(frames)
    hold_frames = max(1, int((duration - trans_frames / FPS) * FPS))
    total_frames = trans_frames + hold_frames

    with tempfile.TemporaryDirectory() as tmp:
        tmp_path = Path(tmp)
        for i, frame in enumerate(frames):
            frame.save(tmp_path / f"frame_{i:05d}.png")
        last = frames[-1]
        for i in range(hold_frames):
            last.save(tmp_path / f"frame_{trans_frames + i:05d}.png")

        run([
            "ffmpeg", "-y",
            "-framerate", str(FPS),
            "-i", str(tmp_path / "frame_%05d.png"),
            "-i", str(audio),
            "-c:v", "libx264", "-pix_fmt", "yuv420p",
            "-c:a", "aac", "-b:a", "128k",
            "-shortest",
            str(out),
        ])
    return total_frames / FPS


def format_ts(seconds: float) -> str:
    ms = int(round(seconds * 1000))
    h, rem = divmod(ms, 3_600_000)
    m, rem = divmod(rem, 60_000)
    s, ms = divmod(rem, 1000)
    return f"{h:02d}:{m:02d}:{s:02d}.{ms:03d}"


def write_vtt(entries: list[tuple[float, float, str]], path: Path) -> None:
    lines = ["WEBVTT", ""]
    for start, end, text in entries:
        lines += [f"{format_ts(start)} --> {format_ts(end)}", text.strip(), ""]
    path.write_text("\n".join(lines), encoding="utf-8")


def make_intro_frames(base: Image.Image, n: int) -> list[Image.Image]:
    frames: list[Image.Image] = []
    for f in range(n):
        t = ease_in_out(f / max(n - 1, 1))
        frames.append(render_overview(base, lerp(1.0, 1.05, t)))
    return frames


def make_focus_frames(
    base: Image.Image,
    from_idx: int | None,
    to_idx: int,
    n: int,
) -> list[Image.Image]:
    cw, ch = canvas_size()
    ox, oy = cw / 2, ch / 2
    tx, ty = tile_center(to_idx)
    frames: list[Image.Image] = []

    if from_idx is None:
        start_x, start_y, start_z = ox, oy, overview_scale(1.0)
    else:
        start_x, start_y = tile_center(from_idx)
        start_z = FOCUS_ZOOM

    for f in range(n):
        t = ease_in_out(f / max(n - 1, 1))
        cx = lerp(start_x, tx, t)
        cy = lerp(start_y, ty, t)
        zoom = lerp(start_z, FOCUS_ZOOM, t)
        focus = to_idx if t >= 0.5 else (from_idx if from_idx is not None else None)
        frames.append(render_viewport(base, cx, cy, zoom, focus_index=focus))

    frames[-1] = render_viewport(base, tx, ty, FOCUS_ZOOM, focus_index=to_idx)
    return frames


async def main() -> int:
    if not EDGE_TTS.is_file():
        print("edge-tts fehlt", file=sys.stderr)
        return 1

    OUT_DIR.mkdir(parents=True, exist_ok=True)
    base = build_dashboard_canvas()
    trans_n = max(1, int(TRANSITION_SEC * FPS))
    segments = [("intro", INTRO, None)] + [(f"tile_{i}", t.narration, i) for i, t in enumerate(TILES)]

    with tempfile.TemporaryDirectory(prefix="dg-dash-vid-") as tmp:
        tmp_path = Path(tmp)
        clips: list[Path] = []
        vtt: list[tuple[float, float, str]] = []
        cursor = 0.0
        manifest: list[dict] = []

        for idx, (key, narration, tile_idx) in enumerate(segments):
            print(f"[{idx + 1}/{len(segments)}] {key}")
            mp3 = tmp_path / f"{key}.mp3"
            clip = tmp_path / f"{key}.mp4"
            await synthesize(narration, mp3)

            if tile_idx is None:
                frames = make_intro_frames(base, trans_n)
            elif tile_idx == 0:
                frames = make_focus_frames(base, None, 0, trans_n)
            else:
                frames = make_focus_frames(base, tile_idx - 1, tile_idx, trans_n)

            dur = encode_clip(frames, mp3, clip)
            clips.append(clip)
            vtt.append((cursor, cursor + dur - 0.05, narration))
            cursor += dur
            manifest.append({"key": key, "duration_sec": round(dur, 2)})

        concat = tmp_path / "concat.txt"
        concat.write_text("\n".join(f"file '{c}'" for c in clips), encoding="utf-8")
        mp4_out = OUT_DIR / "dashboard-ueberblick.mp4"
        vtt_out = OUT_DIR / "dashboard-ueberblick.vtt"
        run(["ffmpeg", "-y", "-f", "concat", "-safe", "0", "-i", str(concat), "-c", "copy", str(mp4_out)])
        write_vtt(vtt, vtt_out)
        (OUT_DIR / "dashboard-ueberblick.meta.json").write_text(
            json.dumps(
                {"title": "Dashboard — Kurzüberblick", "duration_sec": round(cursor, 1), "segments": manifest},
                ensure_ascii=False,
                indent=2,
            ),
            encoding="utf-8",
        )

    print(f"\nFertig: {mp4_out} ({cursor / 60:.1f} min)")
    return 0


if __name__ == "__main__":
    raise SystemExit(asyncio.run(main()))
