#!/usr/bin/env python3
"""Dashboard-Schulungsvideo aus echtem CRM-Screenshot + Kamera-Fokus je Kachel."""

from __future__ import annotations

import asyncio
import json
import subprocess
import sys
import tempfile
from dataclasses import dataclass
from pathlib import Path

from PIL import Image, ImageDraw

ROOT = Path(__file__).resolve().parents[1]
OUT_DIR = ROOT / "storage/media/training/allgemein"
SCREENSHOT = OUT_DIR / "dashboard-capture.png"
TILES_JSON = OUT_DIR / "dashboard-tiles.json"
VOICE = "de-DE-KatjaNeural"
EDGE_TTS = Path.home() / ".local/bin/edge-tts"

OUT_W, OUT_H = 1920, 1080
FPS = 25
TRANSITION_SEC = 0.85
FOCUS_ZOOM = 2.2

INTRO = (
    "Willkommen zur kurzen Einführung ins Dashboard. Nach dem Login sehen Sie hier alle Module als Kacheln — "
    "Ihr Einstieg in das CRM. Links finden Sie dieselben Bereiche im Menü. "
    "In diesem Video erklären wir nur, wofür jede Kachel gedacht ist. "
    "Was Sie nach dem Öffnen sehen, behandeln wir in eigenen Videos."
)

# Erzähltext je Modul-Slug (Reihenfolge kommt aus dem echten Screenshot)
NARRATIONS: dict[str, str] = {
    "kontakte": "Kontakte: Hier werden alle Kontakte Ihres Unternehmens gespeichert — Kunden, Lieferanten, Mitarbeiter und Behörden. Alle für Unternehmen relevanten Kontaktfelder sind voreingestellt, inklusive eines Feldes für Bemerkungen.",
    "terminkalender": "Terminkalender: Termine, Buchungen und Kalenderbereiche im Überblick — für Planung und Abstimmung im Team.",
    "zeiterfassung": "Zeiterfassung: Einstempeln, Pausen und Arbeitszeiten erfassen — Grundlage für HR und Auswertungen.",
    "post": "Post: Ihre Postfächer — Eingang lesen und Nachrichten direkt aus dem CRM versenden.",
    "akademie": "Akademie: Schulungen, Erklärvideos und Zertifikate — hier finden Sie Anleitungen zu den Modulen.",
    "artikel-leistungen": "Artikel und Leistungen: Der Katalog für verkaufte und eingekaufte Artikel sowie Leistungen — Basis für Angebote, Belege und Lager.",
    "lager": "Lager: Bestände, Lagerorte und Bewegungen — von Wareneingang bis Inventur.",
    "support-freigabe": "Support-Freigabe: Temporären Zugang für den Support freischalten — zeitlich begrenzt und kontrolliert.",
    "bilder": "Bilder: Medienbibliothek für Logos, Fotos und andere Bilddateien — nur für Administratoren.",
    "buchhaltung-konten": "Konten: Kontenrahmen und Kontenhinweise — Ihr Nachschlagewerk für die Buchführung.",
    "buchhaltung-belege": "Belege: Eingangs- und Ausgangsbelege erfassen — mit Steuerfeldern und Kontenzuordnung.",
    "buchhaltung-ueberweisungen": "Überweisungen: Zahlungen vorbereiten — inklusive QR-Code und Fotovorlage für den Bankauftrag.",
    "buchhaltung-kontenuebersicht": "Kontenübersicht: Salden und Kontoauszüge je Geschäftsjahr auf einen Blick.",
    "buchhaltung-opos": "Offene Posten: Offene Forderungen und Verbindlichkeiten — wer schuldet wem noch etwas.",
    "buchhaltung-kassenbuch": "Kassenbuch: Bar-Ein- und Ausgänge aus Kassenbelegen dokumentieren.",
    "buchhaltung-manuelle-buchung": "Manuelle Buchungen: Journalbuchungen ohne Beleg — wenn Soll und Haben direkt gebucht werden.",
    "buchhaltung-auswertungen": "Bilanz und GuV: Auswertungen zu Vermögen, Schulden und Ergebnis je Geschäftsjahr.",
    "buchhaltung-bwa": "BWA: Betriebswirtschaftliche Auswertung — der klassische Monatsüberblick fürs Management.",
    "buchhaltung-susa": "SuSa: Summen- und Saldenliste — Kontenstände kompakt für Prüfung und Steuerberater.",
    "buchhaltung-bankabgleich": "Bankabgleich: Kontoauszüge importieren und Belegen zuordnen.",
    "buchhaltung-steuerberater-export": "Steuerberater-Export: Daten für DATEV, Agenda oder Addison — Buchungsstapel und Belege.",
    "buchhaltung-ustva": "UStVA: Umsatzsteuer-Voranmeldung vorbereiten — inklusive ELSTER-Export.",
    "buchhaltung-jahresabschluss": "Jahresabschluss: Checkliste und Assistent für GuV-Abschluss und Saldenvortrag.",
    "website-seiten": "Seiten: Inhaltsseiten der öffentlichen Website anlegen und pflegen.",
    "website-formulare": "Formulare: Kontakt- und Anfrageformulare bauen und Eingänge empfangen.",
    "website-statistik": "Statistik: Seitenaufrufe im CRM und Links zu Google Analytics oder Tag Manager.",
    "website-menu": "Menü: Navigation der Website — welche Seiten wo verlinkt sind.",
    "website-chrome": "Kopf und Fuß: Kopfzeile, Fußzeile und zusätzliche Skripte der Website.",
    "website-design": "Design: Farben und Erscheinungsbild der öffentlichen Website.",
    "einstellungen": "Einstellungen: Firma, E-Mail, Abteilungen, Module und System — nur für berechtigte Nutzer. Das war der Kurzüberblick über alle Dashboard-Kacheln. Wählen Sie eine Kachel, wenn Sie in einem Bereich arbeiten möchten — die Details erklären wir in den Modul-Videos der Akademie. Viel Erfolg!",
}


@dataclass
class TileRect:
    slug: str
    label: str
    x: float
    y: float
    w: float
    h: float

    @property
    def cx(self) -> float:
        return self.x + self.w / 2

    @property
    def cy(self) -> float:
        return self.y + self.h / 2


def ease_in_out(t: float) -> float:
    return t * t * (3 - 2 * t)


def lerp(a: float, b: float, t: float) -> float:
    return a + (b - a) * t


def load_assets() -> tuple[Image.Image, list[TileRect]]:
    if not SCREENSHOT.is_file() or not TILES_JSON.is_file():
        print(
            "Fehlt Screenshot oder Kachel-JSON. Bitte ausführen:\n"
            "  php bin/academy-export-dashboard-html.php\n"
            "  python3 bin/academy-capture-dashboard.py",
            file=sys.stderr,
        )
        sys.exit(1)

    base = Image.open(SCREENSHOT).convert("RGB")
    data = json.loads(TILES_JSON.read_text(encoding="utf-8"))
    tiles = [
        TileRect(
            slug=t["slug"],
            label=t["label"],
            x=float(t["x"]),
            y=float(t["y"]),
            w=float(t["w"]),
            h=float(t["h"]),
        )
        for t in data["tiles"]
        if t.get("slug")
    ]
    if not tiles:
        print("Keine Kacheln in dashboard-tiles.json", file=sys.stderr)
        sys.exit(1)
    return base, tiles


def overview_scale(img_w: int, img_h: int, extra: float = 1.0) -> float:
    return min(OUT_W / img_w, OUT_H / img_h) * extra


def render_overview(base: Image.Image, extra_zoom: float = 1.0) -> Image.Image:
    iw, ih = base.size
    scale = overview_scale(iw, ih, extra_zoom)
    nw, nh = max(1, int(iw * scale)), max(1, int(ih * scale))
    scaled = base.resize((nw, nh), Image.Resampling.LANCZOS)
    frame = Image.new("RGB", (OUT_W, OUT_H), (245, 243, 240))
    frame.paste(scaled, ((OUT_W - nw) // 2, (OUT_H - nh) // 2))
    return frame


def render_viewport(
    base: Image.Image,
    center_x: float,
    center_y: float,
    zoom: float,
    focus: TileRect | None = None,
) -> Image.Image:
    iw, ih = base.size
    view_w = OUT_W / zoom
    view_h = OUT_H / zoom
    left = max(0, min(center_x - view_w / 2, max(0, iw - view_w)))
    top = max(0, min(center_y - view_h / 2, max(0, ih - view_h)))
    right = min(iw, left + view_w)
    bottom = min(ih, top + view_h)
    crop = base.crop((int(left), int(top), int(right), int(bottom)))
    frame = crop.resize((OUT_W, OUT_H), Image.Resampling.LANCZOS)

    if focus is not None:
        sx = (focus.x - left) / max(right - left, 1) * OUT_W
        sy = (focus.y - top) / max(bottom - top, 1) * OUT_H
        sw = focus.w / max(right - left, 1) * OUT_W
        sh = focus.h / max(bottom - top, 1) * OUT_H
        overlay = Image.new("RGBA", (OUT_W, OUT_H), (0, 0, 0, 0))
        od = ImageDraw.Draw(overlay)
        pad = 6
        od.rectangle(
            [sx - pad, sy - pad, sx + sw + pad, sy + sh + pad],
            outline=(110, 98, 88, 255),
            width=4,
        )
        frame = Image.alpha_composite(frame.convert("RGBA"), overlay).convert("RGB")

        mask = Image.new("L", (OUT_W, OUT_H), 0)
        md = ImageDraw.Draw(mask)
        md.rectangle([0, 0, OUT_W, OUT_H], fill=22)
        md.rectangle([sx - 8, sy - 8, sx + sw + 8, sy + sh + 8], fill=0)
        dim = Image.new("RGBA", (OUT_W, OUT_H), (20, 18, 16, 255))
        frame = Image.composite(
            Image.alpha_composite(frame.convert("RGBA"), dim).convert("RGB"),
            frame,
            mask,
        )
    return frame


def make_intro_frames(base: Image.Image, n: int) -> list[Image.Image]:
    return [render_overview(base, lerp(1.0, 1.04, ease_in_out(f / max(n - 1, 1)))) for f in range(n)]


def make_focus_frames(
    base: Image.Image,
    from_tile: TileRect | None,
    to_tile: TileRect,
    n: int,
) -> list[Image.Image]:
    iw, ih = base.size
    ox, oy = iw / 2, ih / 2
    if from_tile is None:
        sx, sy, sz = ox, oy, overview_scale(iw, ih, 1.0)
    else:
        sx, sy, sz = from_tile.cx, from_tile.cy, FOCUS_ZOOM

    frames: list[Image.Image] = []
    for f in range(n):
        t = ease_in_out(f / max(n - 1, 1))
        cx = lerp(sx, to_tile.cx, t)
        cy = lerp(sy, to_tile.cy, t)
        zoom = lerp(sz, FOCUS_ZOOM, t)
        focus = to_tile if t >= 0.5 else from_tile
        frames.append(render_viewport(base, cx, cy, zoom, focus))
    frames[-1] = render_viewport(base, to_tile.cx, to_tile.cy, FOCUS_ZOOM, to_tile)
    return frames


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
    return (trans_frames + hold_frames) / FPS


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


async def main() -> int:
    if not EDGE_TTS.is_file():
        print("edge-tts fehlt", file=sys.stderr)
        return 1

    OUT_DIR.mkdir(parents=True, exist_ok=True)
    base, tiles = load_assets()
    trans_n = max(1, int(TRANSITION_SEC * FPS))

    segments: list[tuple[str, str, TileRect | None]] = [("intro", INTRO, None)]
    for tile in tiles:
        text = NARRATIONS.get(tile.slug)
        if not text:
            text = f"{tile.label}: {tile.label} im CRM."
            print(f"Hinweis: keine Narration für slug={tile.slug}, Fallback genutzt")
        segments.append((tile.slug, text, tile))

    with tempfile.TemporaryDirectory(prefix="dg-dash-vid-") as tmp:
        tmp_path = Path(tmp)
        clips: list[Path] = []
        vtt: list[tuple[float, float, str]] = []
        cursor = 0.0
        manifest: list[dict] = []

        for idx, (key, narration, tile) in enumerate(segments):
            print(f"[{idx + 1}/{len(segments)}] {key}")
            mp3 = tmp_path / f"{key}.mp3"
            clip = tmp_path / f"{key}.mp4"
            await synthesize(narration, mp3)

            if tile is None:
                frames = make_intro_frames(base, trans_n)
            elif idx == 1:
                frames = make_focus_frames(base, None, tile, trans_n)
            else:
                prev = segments[idx - 1][2]
                assert prev is not None
                frames = make_focus_frames(base, prev, tile, trans_n)

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
                {
                    "title": "Dashboard — Kurzüberblick",
                    "source": "dashboard-capture.png",
                    "duration_sec": round(cursor, 1),
                    "segments": manifest,
                },
                ensure_ascii=False,
                indent=2,
            ),
            encoding="utf-8",
        )

    print(f"\nFertig: {mp4_out} ({cursor / 60:.1f} min)")
    return 0


if __name__ == "__main__":
    raise SystemExit(asyncio.run(main()))
