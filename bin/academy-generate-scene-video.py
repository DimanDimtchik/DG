#!/usr/bin/env python3
"""Schulungsvideo aus Szenen (Screenshots, Dashboard-Kachel, Formularfeld-Fokus)."""

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
LOCALES_DIR = ROOT / "docs/akademie/locales"
DASHBOARD_PNG = ROOT / "storage/media/training/allgemein/dashboard-capture.png"
DASHBOARD_TILES = ROOT / "storage/media/training/allgemein/dashboard-tiles.json"
EDGE_TTS = Path.home() / ".local/bin/edge-tts"
DEFAULT_VOICE = "de-DE-KatjaNeural"

OUT_W, OUT_H = 1920, 1080
FPS = 25
TRANSITION_SEC = 1.15
FOCUS_ZOOM = 2.0
MAX_ZOOM = 3.4
MIN_ZOOM = 1.25
FOCUS_OUTLINE = (210, 45, 45, 255)


@dataclass
class Rect:
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


def overview_scale(iw: int, ih: int, extra: float = 1.0) -> float:
    return min(OUT_W / iw, OUT_H / ih) * extra


def fit_zoom(rect: Rect, pad_x: float = 56, pad_y: float = 40) -> float:
    zw = OUT_W / max(rect.w + pad_x * 2, 1)
    zh = OUT_H / max(rect.h + pad_y * 2, 1)
    return max(MIN_ZOOM, min(zw, zh, MAX_ZOOM))


def render_overview(base: Image.Image, extra: float = 1.0) -> Image.Image:
    iw, ih = base.size
    scale = overview_scale(iw, ih, extra)
    nw, nh = max(1, int(iw * scale)), max(1, int(ih * scale))
    scaled = base.resize((nw, nh), Image.Resampling.LANCZOS)
    frame = Image.new("RGB", (OUT_W, OUT_H), (245, 243, 240))
    frame.paste(scaled, ((OUT_W - nw) // 2, (OUT_H - nh) // 2))
    return frame


def render_viewport(base: Image.Image, cx: float, cy: float, zoom: float, focus: Rect | None = None) -> Image.Image:
    iw, ih = base.size
    view_w, view_h = OUT_W / zoom, OUT_H / zoom
    left = max(0, min(cx - view_w / 2, max(0, iw - view_w)))
    top = max(0, min(cy - view_h / 2, max(0, ih - view_h)))
    right, bottom = min(iw, left + view_w), min(ih, top + view_h)
    frame = base.crop((int(left), int(top), int(right), int(bottom))).resize((OUT_W, OUT_H), Image.Resampling.LANCZOS)
    if focus is None:
        return frame
    sx = (focus.x - left) / max(right - left, 1) * OUT_W
    sy = (focus.y - top) / max(bottom - top, 1) * OUT_H
    sw = focus.w / max(right - left, 1) * OUT_W
    sh = focus.h / max(bottom - top, 1) * OUT_H
    overlay = Image.new("RGBA", (OUT_W, OUT_H), (0, 0, 0, 0))
    od = ImageDraw.Draw(overlay)
    pad = 6
    od.rectangle([sx - pad, sy - pad, sx + sw + pad, sy + sh + pad], outline=FOCUS_OUTLINE, width=4)
    frame = Image.alpha_composite(frame.convert("RGBA"), overlay).convert("RGB")
    mask = Image.new("L", (OUT_W, OUT_H), 0)
    md = ImageDraw.Draw(mask)
    md.rectangle([0, 0, OUT_W, OUT_H], fill=22)
    md.rectangle([sx - 8, sy - 8, sx + sw + 8, sy + sh + 8], fill=0)
    dim = Image.new("RGBA", (OUT_W, OUT_H), (20, 18, 16, 255))
    return Image.composite(Image.alpha_composite(frame.convert("RGBA"), dim).convert("RGB"), frame, mask)


def load_tiles() -> dict[str, Rect]:
    data = json.loads(DASHBOARD_TILES.read_text(encoding="utf-8"))
    return {
        t["slug"]: Rect(float(t["x"]), float(t["y"]), float(t["w"]), float(t["h"]))
        for t in data["tiles"]
        if t.get("slug")
    }


def load_regions(path: Path) -> tuple[dict[str, Rect], dict[str, Rect]]:
    if not path.is_file():
        return {}, {}
    data = json.loads(path.read_text(encoding="utf-8"))
    fields: dict[str, Rect] = {}
    sections: dict[str, Rect] = {}
    for key, r in (data.get("fields") or {}).items():
        fields[key] = Rect(float(r["x"]), float(r["y"]), float(r["w"]), float(r["h"]))
    for key, r in (data.get("sections") or {}).items():
        sections[key] = Rect(float(r["x"]), float(r["y"]), float(r["w"]), float(r["h"]))
    return fields, sections


def make_field_chain_frames(
    base: Image.Image,
    focus: Rect,
    n: int,
    from_field: Rect,
) -> list[Image.Image]:
    """Weiteres Feld im gleichen Abschnitt: sanft vom vorherigen Feld zum nächsten."""
    field_z = fit_zoom(focus, 48, 36)
    from_z = fit_zoom(from_field, 48, 36)
    n1 = max(1, (n * 2) // 3)
    n2 = max(1, n - n1)
    frames: list[Image.Image] = []
    for f in range(n1):
        t = ease_in_out(f / max(n1 - 1, 1))
        cx = lerp(from_field.cx, focus.cx, t)
        cy = lerp(from_field.cy, focus.cy, t)
        z = lerp(from_z, field_z, t)
        fr = focus if t >= 0.55 else from_field
        frames.append(render_viewport(base, cx, cy, z, fr))
    for _ in range(n2):
        frames.append(render_viewport(base, focus.cx, focus.cy, field_z, focus))
    frames[-1] = render_viewport(base, focus.cx, focus.cy, field_z, focus)
    return frames


def make_field_section_start_frames(
    base: Image.Image,
    focus: Rect,
    section: Rect,
    n: int,
) -> list[Image.Image]:
    """Neuer Abschnitt: Gesamtansicht → Bereich → erstes Feld."""
    iw, ih = base.size
    ox, oy = iw / 2, ih / 2
    overview_z = overview_scale(iw, ih, 1.0)
    section_z = fit_zoom(section, 72, 56)
    field_z = fit_zoom(focus, 48, 36)
    sec_cx, sec_cy = section.cx, section.cy
    fld_cx, fld_cy = focus.cx, focus.cy

    n1 = max(1, n // 3)
    n2 = max(1, n // 3)
    n3 = max(1, n - n1 - n2)
    frames: list[Image.Image] = []

    for f in range(n1):
        t = ease_in_out(f / max(n1 - 1, 1))
        cx, cy = lerp(ox, sec_cx, t), lerp(oy, sec_cy, t)
        z = lerp(overview_z, section_z, t)
        fr = section if t >= 0.55 else None
        frames.append(render_viewport(base, cx, cy, z, fr))

    for f in range(n2):
        t = ease_in_out(f / max(n2 - 1, 1))
        cx, cy = lerp(sec_cx, fld_cx, t), lerp(sec_cy, fld_cy, t)
        z = lerp(section_z, field_z, t)
        fr = focus if t >= 0.55 else section
        frames.append(render_viewport(base, cx, cy, z, fr))

    for _ in range(n3):
        frames.append(render_viewport(base, fld_cx, fld_cy, field_z, focus))
    frames[-1] = render_viewport(base, fld_cx, fld_cy, field_z, focus)
    return frames


def make_frames(
    base: Image.Image,
    mode: str,
    focus: Rect | None,
    n: int,
    section: Rect | None = None,
    zoom: float = FOCUS_ZOOM,
    chain_field: bool = False,
    from_field: Rect | None = None,
) -> list[Image.Image]:
    iw, ih = base.size
    ox, oy = iw / 2, ih / 2
    overview_z = overview_scale(iw, ih, 1.0)

    if mode == "overview":
        return [render_overview(base, lerp(1.0, 1.03, ease_in_out(f / max(n - 1, 1)))) for f in range(n)]

    if mode == "focus_tile" and focus is not None:
        frames: list[Image.Image] = []
        for f in range(n):
            t = ease_in_out(f / max(n - 1, 1))
            cx, cy = lerp(ox, focus.cx, t), lerp(oy, focus.cy, t)
            z = lerp(overview_z, zoom, t)
            fr = focus if t >= 0.5 else None
            frames.append(render_viewport(base, cx, cy, z, fr))
        frames[-1] = render_viewport(base, focus.cx, focus.cy, zoom, focus)
        return frames

    if mode == "focus_field" and focus is not None:
        if chain_field and from_field is not None:
            return make_field_chain_frames(base, focus, n, from_field)
        sec = section or focus
        return make_field_section_start_frames(base, focus, sec, n)

    return [render_overview(base) for _ in range(n)]


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


async def synthesize(text: str, mp3: Path, voice: str) -> None:
    proc = await asyncio.create_subprocess_exec(str(EDGE_TTS), "--voice", voice, "--text", text, "--write-media", str(mp3))
    if await proc.wait() != 0:
        raise RuntimeError("edge-tts failed")


def encode_clip(frames: list[Image.Image], audio: Path, out: Path) -> float:
    duration = probe_duration(audio)
    trans = len(frames)
    hold = max(1, int((duration - trans / FPS) * FPS))
    with tempfile.TemporaryDirectory() as tmp:
        tmp_path = Path(tmp)
        for i, frame in enumerate(frames):
            frame.save(tmp_path / f"frame_{i:05d}.png")
        last = frames[-1]
        for i in range(hold):
            last.save(tmp_path / f"frame_{trans + i:05d}.png")
        run(
            [
                "ffmpeg",
                "-y",
                "-framerate",
                str(FPS),
                "-i",
                str(tmp_path / "frame_%05d.png"),
                "-i",
                str(audio),
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
    return (trans + hold) / FPS


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


def resolve_path(rel: str) -> Path:
    p = ROOT / rel
    if not p.is_file():
        p = Path(rel)
    return p


async def main() -> int:
    import argparse

    parser = argparse.ArgumentParser()
    parser.add_argument("--locale", default="de")
    parser.add_argument("--script", required=True, help="video_slug in locales/de/")
    parser.add_argument("--out-dir", default="storage/media/training/kontakte")
    args = parser.parse_args()

    script_path = LOCALES_DIR / args.locale / f"{args.script}.json"
    if not script_path.is_file():
        print(f"Fehlt: {script_path}", file=sys.stderr)
        return 1
    spec = json.loads(script_path.read_text(encoding="utf-8"))
    voice = str(spec.get("voice", DEFAULT_VOICE))
    segments = spec.get("segments", [])
    if not segments:
        print("Keine segments", file=sys.stderr)
        return 1

    tiles = load_tiles() if DASHBOARD_TILES.is_file() else {}
    out_dir = ROOT / args.out_dir
    out_dir.mkdir(parents=True, exist_ok=True)
    base_name = spec["video_slug"] if args.locale == "de" else f"{spec['video_slug']}.{args.locale}"
    trans_n = max(1, int(TRANSITION_SEC * FPS))

    image_cache: dict[str, Image.Image] = {}
    region_cache: dict[str, tuple[dict[str, Rect], dict[str, Rect]]] = {}

    def get_image(rel: str) -> Image.Image:
        if rel not in image_cache:
            path = resolve_path(rel)
            if not path.is_file():
                raise FileNotFoundError(path)
            image_cache[rel] = Image.open(path).convert("RGB")
        return image_cache[rel]

    with tempfile.TemporaryDirectory(prefix="dg-scene-vid-") as tmp:
        tmp_path = Path(tmp)
        clips: list[Path] = []
        vtt: list[tuple[float, float, str]] = []
        cursor = 0.0
        manifest: list[dict] = []

        prev_field: Rect | None = None
        prev_section_key: str | None = None
        prev_screenshot: str | None = None

        for idx, seg in enumerate(segments):
            key = seg["key"]
            narration = seg["narration"]
            print(f"[{idx + 1}/{len(segments)}] {key}")
            screenshot = seg.get("screenshot", "")
            mode = seg.get("mode", "overview")
            base = get_image(screenshot)

            focus: Rect | None = None
            section: Rect | None = None
            chain_field = False
            from_field: Rect | None = None

            if mode == "overview":
                prev_field = None
                prev_section_key = None
                prev_screenshot = None

            if mode == "focus_tile":
                focus = tiles.get(seg.get("tile_slug", ""))
            elif mode == "focus_field":
                regions_file = seg.get("regions_file", "")
                if regions_file not in region_cache:
                    region_cache[regions_file] = load_regions(resolve_path(regions_file))
                fields, sections = region_cache[regions_file]
                field_key = seg.get("field", "")
                focus = fields.get(field_key)
                section_key = seg.get("section", "")
                section = sections.get(section_key)
                if focus is None:
                    print(f"Warnung: Feld {field_key} nicht gefunden", file=sys.stderr)
                if section is None and section_key:
                    print(f"Warnung: Abschnitt {section_key} nicht gefunden", file=sys.stderr)
                if (
                    focus is not None
                    and prev_field is not None
                    and section_key
                    and section_key == prev_section_key
                    and screenshot == prev_screenshot
                ):
                    chain_field = True
                    from_field = prev_field

            mp3 = tmp_path / f"{key}.mp3"
            clip = tmp_path / f"{key}.mp4"
            await synthesize(narration, mp3, voice)
            frames = make_frames(
                base,
                mode,
                focus,
                trans_n,
                section=section,
                chain_field=chain_field,
                from_field=from_field,
            )
            dur = encode_clip(frames, mp3, clip)
            if mode == "focus_field" and focus is not None:
                prev_field = focus
                prev_section_key = seg.get("section") or prev_section_key
                prev_screenshot = screenshot
            clips.append(clip)
            vtt.append((cursor, cursor + dur - 0.05, narration))
            cursor += dur
            manifest.append({"key": key, "duration_sec": round(dur, 2)})

        concat = tmp_path / "concat.txt"
        concat.write_text("\n".join(f"file '{c}'" for c in clips), encoding="utf-8")
        mp4_out = out_dir / f"{base_name}.mp4"
        vtt_out = out_dir / f"{base_name}.vtt"
        run(["ffmpeg", "-y", "-f", "concat", "-safe", "0", "-i", str(concat), "-c", "copy", str(mp4_out)])
        write_vtt(vtt, vtt_out)
        (out_dir / f"{base_name}.meta.json").write_text(
            json.dumps(
                {
                    "title": spec.get("title"),
                    "locale": args.locale,
                    "voice": voice,
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
