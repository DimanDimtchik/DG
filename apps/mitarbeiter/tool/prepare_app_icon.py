"""Trim MA logo and write launcher ICO."""
from __future__ import annotations

from pathlib import Path

from PIL import Image

ROOT = Path(__file__).resolve().parents[1]
SRC = ROOT / "assets" / "branding" / "app_icon.png"
ICO = ROOT / "windows" / "runner" / "resources" / "app_icon.ico"


def is_bg(px: tuple[int, int, int, int]) -> bool:
    r, g, b, a = px
    if a < 20:
        return True
    return r > 245 and g > 245 and b > 245


def main() -> None:
    im = Image.open(SRC).convert("RGBA")
    pixels = im.load()
    w, h = im.size
    minx, miny, maxx, maxy = w, h, 0, 0
    found = False
    for y in range(h):
        for x in range(w):
            if not is_bg(pixels[x, y]):
                found = True
                minx = min(minx, x)
                miny = min(miny, y)
                maxx = max(maxx, x)
                maxy = max(maxy, y)
    if not found:
        raise SystemExit("no content found in icon")

    pad = 8
    minx = max(0, minx - pad)
    miny = max(0, miny - pad)
    maxx = min(w - 1, maxx + pad)
    maxy = min(h - 1, maxy + pad)
    cropped = im.crop((minx, miny, maxx + 1, maxy + 1))

    side = max(cropped.size)
    canvas = Image.new("RGBA", (side, side), (15, 118, 110, 255))
    ox = (side - cropped.size[0]) // 2
    oy = (side - cropped.size[1]) // 2
    canvas.paste(cropped, (ox, oy), cropped)
    out = canvas.resize((1024, 1024), Image.Resampling.LANCZOS)
    out.save(SRC)
    out.convert("RGB").save(ROOT / "assets" / "branding" / "app_icon_rgb.png", quality=95)

    sizes = [16, 32, 48, 64, 128, 256]
    icons = [out.resize((s, s), Image.Resampling.LANCZOS) for s in sizes]
    ICO.parent.mkdir(parents=True, exist_ok=True)
    icons[0].save(ICO, format="ICO", sizes=[(s, s) for s in sizes], append_images=icons[1:])
    print(f"trimmed {im.size} -> {cropped.size} -> {out.size}")
    print(f"wrote {SRC}")
    print(f"wrote {ICO}")


if __name__ == "__main__":
    main()
