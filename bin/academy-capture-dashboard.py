#!/usr/bin/env python3
"""Screenshot des echten CRM-Dashboards (HTML-Export) + Kachel-Positionen."""

from __future__ import annotations

import json
import sys
from pathlib import Path

from PIL import Image
from playwright.sync_api import sync_playwright

ROOT = Path(__file__).resolve().parents[1]
OUT_DIR = ROOT / "storage/media/training/allgemein"
HTML = OUT_DIR / "dashboard-capture.html"
PNG = OUT_DIR / "dashboard-capture.png"
TILES_JSON = OUT_DIR / "dashboard-tiles.json"
VIEWPORT_W = 1920
VIEWPORT_H = 1080


def main() -> int:
    if not HTML.is_file():
        print(f"Fehlt: {HTML} — zuerst bin/academy-export-dashboard-html.php ausführen", file=sys.stderr)
        return 1

    OUT_DIR.mkdir(parents=True, exist_ok=True)

    with sync_playwright() as p:
        browser = p.chromium.launch(headless=True)
        page = browser.new_page(viewport={"width": VIEWPORT_W, "height": VIEWPORT_H})
        page.goto(HTML.as_uri(), wait_until="networkidle", timeout=120_000)
        page.wait_for_timeout(800)
        page.evaluate(
            """() => {
                const el = document.getElementById('dg-cookie-consent');
                if (el) el.remove();
                const style = document.createElement('style');
                style.textContent = `
                    body.dg-app { height: auto !important; min-height: auto !important; overflow: visible !important; }
                    .dg-shell { overflow: visible !important; min-height: auto !important; }
                    .dg-content { overflow: visible !important; height: auto !important; min-height: auto !important; }
                `;
                document.head.appendChild(style);
                window.scrollTo(0, 0);
            }"""
        )
        page.wait_for_timeout(300)
        page.screenshot(path=str(PNG), full_page=True)

        img_h = Image.open(PNG).size[1]
        page.set_viewport_size({"width": VIEWPORT_W, "height": img_h})
        page.wait_for_timeout(200)

        tiles = page.evaluate(
            """() => {
                window.scrollTo(0, 0);
                const cards = Array.from(document.querySelectorAll('.dg-grid .dg-card'));
                return cards.map((el) => {
                    const r = el.getBoundingClientRect();
                    const title = el.querySelector('h2')?.textContent?.trim() ?? '';
                    const desc = el.querySelector('p')?.textContent?.trim() ?? '';
                    return {
                        x: Math.round(r.left + window.scrollX),
                        y: Math.round(r.top + window.scrollY),
                        w: Math.round(r.width),
                        h: Math.round(r.height),
                        label: title,
                        description: desc,
                    };
                });
            }"""
        )

        meta_path = OUT_DIR / "dashboard-capture.meta.json"
        slug_map: dict[str, str] = {}
        if meta_path.is_file():
            meta = json.loads(meta_path.read_text(encoding="utf-8"))
            for t in meta.get("tiles", []):
                slug_map[t.get("label", "")] = t.get("slug", "")

        for t in tiles:
            t["slug"] = slug_map.get(t["label"], "")

        payload = {
            "width": VIEWPORT_W,
            "height": img_h,
            "tiles": tiles,
        }
        TILES_JSON.write_text(json.dumps(payload, ensure_ascii=False, indent=2), encoding="utf-8")
        browser.close()

    print(f"Screenshot: {PNG} ({PNG.stat().st_size // 1024} KB, {img_h}px hoch)")
    print(f"Kacheln: {len(tiles)} → {TILES_JSON}")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
