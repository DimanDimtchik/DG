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
TRANSITION_SEC = 0.85
FOCUS_ZOOM = 2.15
FIELD_ZOOM = 2.8


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
    od.rectangle([sx - pad, sy - pad, sx + sw + pad, sy + sh + pad], outline=(110, 98, 88, 255), width=4)
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


def load_regions(path: Path) -> dict[str, Rect]:
    if not path.is_file():
        return {}
    data = json.loads(path.read_text(encoding="utf-8"))
    out: dict[str, Rect] = {}
    for key, r in (data.get("fields") or {}).items():
        out[key] = Rect(float(r["x"]), float(r["y"]), float(r["w"]), float(r["h"]))
    return out


def make_frames(base: Image.Image, mode: str, focus: Rect | None, n: int, from_focus: Rect | None = None, zoom: float = FOCUS_ZOOM) -> list[Image.Image]:
    iw, ih = base.size
    ox, oy = iw / 2, ih / 2
    frames: list[Image.Image] = []

    if mode == "overview":
        return [render_overview(base, lerp(1.0, 1.03, ease_in_out(f / max(n - 1, 1)))) for f in range(n)]

    if mode == "focus_tile" and focus is not None:
        sz = overview_scale(iw, ih, 1.0)
        for f in range(n):
            t = ease_in_out(f / max(n - 1, 1))
            cx, cy = lerp(ox, focus.cx, t), lerp(oy, focus.cy, t)
            z = lerp(sz, zoom, t)
            fr = focus if t >= 0.5 else None
            frames.append(render_viewport(base, cx, cy, z, fr))
        frames[-1] = render_viewport(base, focus.cx, focus.cy, zoom, focus)
        return frames

    if mode == "focus_field" and focus is not None:
        sx, sy = (from_focus.cx, from_focus.cy) if from_focus else (ox, oy)
        sz = FOCUS_ZOOM if from_focus else overview_scale(iw, ih, 1.0)
        zt = FIELD_ZOOM
        for f in range(n):
            t = ease_in_out(f / max(n - 1, 1))
            cx, cy = lerp(sx, focus.cx, t), lerp(sy, focus.cy, t)
            z = lerp(sz, zt, t)
            fr = focus if t >= 0.5 else None
            frames.append(render_viewport(base, cx, cy, z, fr))
        frames[-1] = render_viewport(base, focus.cx, focus.cy, zt, focus)
        return frames

    return [render_overview(base) for _ in range(n)]


def run(cmd: list[str]) -> None:
    subprocess.run(cmd, check=True)


def probe_duration(path: Path) -> float:
    out = subprocess.check_output(
        ["ffprobe", "-v", "error", "-show_entries", "format=duration", "-of", "default=noprint_wrappers=1:nokey=1", str(path)],
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
        run([
            "ffmpeg", "-y", "-framerate", str(FPS), "-i", str(tmp_path / "frame_%05d.png"),
            "-i", str(audio), "-c:v", "libx264", "-pix_fmt", "yuv420p", "-c:a", "aac", "-b:a", "128k",
            "-shortest", str(out),
        ])
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
    region_cache: dict[str, dict[str, Rect]] = {}
    prev_field: Rect | None = None

    def get_image(rel: str) -> Image.Image:
        key = rel
        if key not in image_cache:
            path = resolve_path(rel)
            if not path.is_file():
                raise FileNotFoundError(path)
            image_cache[key] = Image.open(path).convert("RGB")
        return image_cache[key]

    with tempfile.TemporaryDirectory(prefix="dg-scene-vid-") as tmp:
        tmp_path = Path(tmp)
        clips: list[Path] = []
        vtt: list[tuple[float, float, str]] = []
        cursor = 0.0
        manifest: list[dict] = []

        for idx, seg in enumerate(segments):
            key = seg["key"]
            narration = seg["narration"]
            print(f"[{idx + 1}/{len(segments)}] {key}")
            screenshot = seg.get("screenshot", "")
            mode = seg.get("mode", "overview")
            base = get_image(screenshot)

            focus: Rect | None = None
            from_focus = prev_field if mode == "focus_field" and seg.get("chain_fields") else None

            if mode == "focus_tile":
                slug = seg.get("tile_slug", "")
                focus = tiles.get(slug)
            elif mode == "focus_field":
                regions_file = seg.get("regions_file", "")
                if regions_file not in region_cache:
                    region_cache[regions_file] = load_regions(resolve_path(regions_file))
                field = seg.get("field", "")
                focus = region_cache[regions_file].get(field)
                if focus is None:
                    print(f"Warnung: Feld {field} nicht gefunden", file=sys.stderr)

            mp3 = tmp_path / f"{key}.mp3"
            clip = tmp_path / f"{key}.mp4"
            await synthesize(narration, mp3, voice)
            frames = make_frames(base, mode, focus, trans_n, from_focus)
            dur = encode_clip(frames, mp3, clip)
            clips.append(clip)
            vtt.append((cursor, cursor + dur - 0.05, narration))
            cursor += dur
            manifest.append({"key": key, "duration_sec": round(dur, 2)})
            if mode == "focus_field" and focus is not None:
                prev_field = focus
            elif mode != "focus_field":
                prev_field = None

        concat = tmp_path / "concat.txt"
        concat.write_text("\n".join(f"file '{c}'" for c in clips), encoding="utf-8")
        mp4_out = out_dir / f"{base_name}.mp4"
        vtt_out = out_dir / f"{base_name}.vtt"
        run(["ffmpeg", "-y", "-f", "concat", "-safe", "0", "-i", str(concat), "-c", "copy", str(mp4_out)])
        write_vtt(vtt, vtt_out)
        (out_dir / f"{base_name}.meta.json").write_text(
            json.dumps({"title": spec.get("title"), "locale": args.locale, "voice": voice, "duration_sec": round(cursor, 1), "segments": manifest}, ensure_ascii=False, indent=2),
            encoding="utf-8",
        )
    print(f"\nFertig: {mp4_out} ({cursor / 60:.1f} min)")
    return 0


if __name__ == "__main__":
    raise SystemExit(asyncio.run(main()))
