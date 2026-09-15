#!/usr/bin/env python3
"""Screenshot einer exportierten CRM-HTML-Seite + optionale Feld-Koordinaten."""

from __future__ import annotations

import json
import sys
from pathlib import Path

from PIL import Image
from playwright.sync_api import sync_playwright

ROOT = Path(__file__).resolve().parents[1]
VIEWPORT_W = 1920


def inject_cleanup(page) -> None:
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


def capture(html: Path, png: Path, regions: Path | None = None, selectors: list[str] | None = None) -> None:
    png.parent.mkdir(parents=True, exist_ok=True)
    with sync_playwright() as p:
        browser = p.chromium.launch(headless=True)
        page = browser.new_page(viewport={"width": VIEWPORT_W, "height": 1080})
        page.goto(html.as_uri(), wait_until="networkidle", timeout=120_000)
        page.wait_for_timeout(800)
        inject_cleanup(page)
        page.wait_for_timeout(300)
        page.screenshot(path=str(png), full_page=True)

        img_h = Image.open(png).size[1]
        page.set_viewport_size({"width": VIEWPORT_W, "height": img_h})
        page.wait_for_timeout(200)

        field_map: dict[str, dict] = {}
        if selectors:
            for sel in selectors:
                data = page.evaluate(
                    """(selector) => {
                        const el = document.querySelector(selector);
                        if (!el) return null;
                        const r = el.getBoundingClientRect();
                        const label = el.closest('label')?.querySelector('span')?.textContent?.trim()
                            || el.getAttribute('name') || selector;
                        return {
                            x: Math.round(r.left + window.scrollX),
                            y: Math.round(r.top + window.scrollY),
                            w: Math.round(r.width),
                            h: Math.round(r.height),
                            label,
                        };
                    }""",
                    sel,
                )
                if data:
                    name = sel.split('name="')[1].split('"')[0] if 'name="' in sel else sel
                    field_map[name] = data

        if regions:
            regions.write_text(
                json.dumps({"width": VIEWPORT_W, "height": img_h, "fields": field_map}, ensure_ascii=False, indent=2),
                encoding="utf-8",
            )
        browser.close()

    print(f"Screenshot: {png} ({Image.open(png).size[1]}px)")
    if regions and regions.is_file():
        print(f"Felder: {len(json.loads(regions.read_text())['fields'])} → {regions}")


def main() -> int:
    if len(sys.argv) < 3:
        print("Usage: academy-capture-page.py input.html output.png [regions.json] [selector ...]", file=sys.stderr)
        return 1
    html = Path(sys.argv[1])
    png = Path(sys.argv[2])
    regions = Path(sys.argv[3]) if len(sys.argv) > 3 and sys.argv[3].endswith(".json") else None
    sel_start = 4 if regions else 3
    selectors = sys.argv[sel_start:] if len(sys.argv) > sel_start else None
    if not html.is_file():
        print(f"Fehlt: {html}", file=sys.stderr)
        return 1
    capture(html, png, regions, selectors)
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
