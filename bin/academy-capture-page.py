#!/usr/bin/env python3
"""Screenshot einer exportierten CRM-HTML-Seite + Feld- und Abschnitts-Koordinaten."""

from __future__ import annotations

import json
import sys
from pathlib import Path

from PIL import Image
from playwright.sync_api import sync_playwright

ROOT = Path(__file__).resolve().parents[1]
VIEWPORT_W = 1920

SECTION_JS = """() => {
    const scrollX = window.scrollX;
    const scrollY = window.scrollY;

    function rect(el) {
        if (!el || el.offsetParent === null && !el.getClientRects().length) return null;
        const style = window.getComputedStyle(el);
        if (style.display === 'none' || style.visibility === 'hidden') return null;
        const r = el.getBoundingClientRect();
        if (r.width < 2 || r.height < 2) return null;
        return {
            x: Math.round(r.left + scrollX),
            y: Math.round(r.top + scrollY),
            w: Math.round(r.width),
            h: Math.round(r.height),
        };
    }

    function union(a, b) {
        if (!a) return b;
        if (!b) return a;
        const x1 = Math.min(a.x, b.x);
        const y1 = Math.min(a.y, b.y);
        const x2 = Math.max(a.x + a.w, b.x + b.w);
        const y2 = Math.max(a.y + a.h, b.y + b.h);
        return { x: x1, y: y1, w: x2 - x1, h: y2 - y1 };
    }

    function expandBlock(startEl) {
        let r = rect(startEl);
        let el = startEl.nextElementSibling;
        while (el) {
            if (el.matches('h2, h3')) break;
            if (el.matches('section[data-company-section], section[data-person-employer-section], .dg-employee-section')) break;
            r = union(r, rect(el));
            el = el.nextElementSibling;
        }
        return r;
    }

    const form = document.querySelector('form.dg-form');
    const sections = {};

    const h2Titles = {
        'Stamm': 'stamm',
        'Kunde / Lieferant': 'kunde_lieferant',
        'Kommunikation': 'kommunikation',
        'Adresse': 'adresse',
        'Bankverbindung': 'bank',
        'Soziale Medien': 'social',
        'Mitarbeiterdaten': 'mitarbeiterdaten',
    };

    if (form) {
        form.querySelectorAll(':scope > h2').forEach((h2) => {
            const key = h2Titles[h2.textContent.trim()];
            if (!key) return;
            const block = expandBlock(h2);
            if (block) sections[key] = block;
        });
    }

    const nested = [
        ['mitarbeiter_firma', '[data-company-section]'],
        ['arbeitgeber', '[data-person-employer-section]'],
        ['mitarbeiterdaten', '[data-employee-section]'],
    ];
    for (const [key, sel] of nested) {
        const el = document.querySelector(sel);
        if (el && !el.hidden) {
            const block = rect(el);
            if (block) sections[key] = block;
        }
    }

    const formGrid = document.querySelector('#dg-booking-form .dg-form-grid, form.dg-form .dg-form-grid');
    if (formGrid) {
        const block = rect(formGrid);
        if (block) sections['form'] = block;
    }

    const searchForm = document.querySelector('form.dg-search');
    if (searchForm) {
        const block = rect(searchForm);
        if (block) sections['list'] = block;
    }

    const tkBook = document.querySelector('.tk-book__card');
    if (tkBook) {
        const block = rect(tkBook);
        if (block) sections['booking'] = block;
    }

    const embedMain = document.querySelector('.dg-settings-main__body');
    if (embedMain && document.querySelector('[name="online_booking_enabled"]')) {
        const block = rect(embedMain);
        if (block) sections['embed'] = block;
    }

    const emailFrame = document.getElementById('dg-academy-email-frame');
    if (emailFrame) {
        const block = rect(emailFrame);
        if (block) sections['email'] = block;
    }

    const subtabs = document.querySelector('.dg-subtabs');
    if (subtabs) {
        const block = rect(subtabs);
        if (block) sections['subtabs'] = block;
    }

    const placeCheck = document.querySelector('#dg-place-audit-form, #dg-place-audit-manual-form');
    if (placeCheck) {
        const parent = placeCheck.closest('.dg-panel') || placeCheck;
        const block = rect(parent);
        if (block) sections['platz_check'] = block;
    }

    const receiptScan = document.querySelector('[data-lager-scan="receipt"]');
    if (receiptScan) {
        const block = rect(receiptScan.closest('.dg-panel') || receiptScan);
        if (block) sections['wareneingang'] = block;
    }

    const issueForm = document.querySelector('#dg-issue-voucher');
    if (issueForm) {
        const block = rect(issueForm.closest('.dg-panel') || issueForm.closest('form') || issueForm);
        if (block) sections['warenausgang'] = block;
    }

    const inventurForm = document.querySelector('input[name="inventory_date"]');
    if (inventurForm) {
        const block = rect(inventurForm.closest('.dg-panel') || inventurForm.closest('form') || inventurForm);
        if (block) sections['inventur'] = block;
    }

    const kontenSearch = document.querySelector('.dg-buchhaltung-konten__search');
    if (kontenSearch) {
        const block = rect(kontenSearch);
        if (block) sections['search'] = block;
    }

    const hintPanel = document.getElementById('dg-account-hint-panel');
    if (hintPanel && !hintPanel.hidden) {
        const block = rect(hintPanel);
        if (block) sections['hint'] = block;
    }

    const kichelFab = document.querySelector('[data-kichel-fab]');
    if (kichelFab) {
        const style = window.getComputedStyle(kichelFab);
        const r = kichelFab.getBoundingClientRect();
        if (r.width >= 2 && r.height >= 2) {
            sections['kichel'] = style.position === 'fixed'
                ? { x: Math.round(r.left), y: Math.round(r.top), w: Math.round(r.width), h: Math.round(r.height) }
                : rect(kichelFab);
        }
    }

    const kichelPanel = document.getElementById('dg-kichel-panel');
    if (kichelPanel && !kichelPanel.hidden) {
        const style = window.getComputedStyle(kichelPanel);
        const r = kichelPanel.getBoundingClientRect();
        if (r.width >= 2 && r.height >= 2) {
            sections['kichel_open'] = style.position === 'fixed'
                ? { x: Math.round(r.left), y: Math.round(r.top), w: Math.round(r.width), h: Math.round(r.height) }
                : rect(kichelPanel);
        }
    }

    const ledgerFilters = document.querySelector('.dg-ledger-filters');
    if (ledgerFilters) {
        const block = rect(ledgerFilters);
        if (block) sections['filter'] = block;
    }

    const manualRoot = document.querySelector('.dg-buchhaltung-manuelle-buchung');
    if (manualRoot) {
        const formPanel = manualRoot.querySelector('#dg-manual-lines')?.closest('.dg-panel');
        if (formPanel) {
            const block = rect(formPanel);
            if (block) sections['form'] = block;
        }
        const kopf = manualRoot.querySelector('form.dg-form .dg-form-grid');
        if (kopf) {
            const block = rect(kopf);
            if (block) sections['kopf'] = block;
        }
        const zeilen = document.getElementById('dg-manual-lines');
        if (zeilen) {
            const wrap = zeilen.closest('.dg-table-wrap') || zeilen;
            const block = rect(wrap);
            if (block) sections['zeilen'] = block;
        }
        const speichern = manualRoot.querySelector('form.dg-form .dg-form-actions, form.dg-form button[type="submit"]');
        if (speichern) {
            const block = rect(speichern);
            if (block) sections['speichern'] = block;
        }
        manualRoot.querySelectorAll('section.dg-panel').forEach((panel) => {
            const h2 = panel.querySelector('h2');
            if (h2 && /Buchungen/i.test(h2.textContent || '')) {
                const block = rect(panel);
                if (block) sections['liste'] = block;
            }
        });
    }

    const mediaEdit = document.querySelector('.dg-media-edit');
    if (mediaEdit) {
        const preview = mediaEdit.querySelector('.dg-media-edit-preview');
        if (preview) {
            const block = rect(preview);
            if (block) sections['vorschau'] = block;
        }
        const usage = mediaEdit.querySelector('.dg-media-edit-usage');
        if (usage) {
            const block = rect(usage);
            if (block) sections['verwendung'] = block;
        }
        const panelTitles = {
            'Metadaten': 'metadaten',
            'Bild & Metadaten': 'metadaten',
            'Größe & Format': 'groesse',
            'Zuschneiden': 'zuschneiden',
            'Hintergrund entfernen / Freistellen': 'freistellen',
            'SVG bearbeiten': 'svg',
            'Löschen': 'loeschen',
        };
        mediaEdit.querySelectorAll('.dg-media-edit-forms .dg-panel, .dg-media-edit-forms form.dg-panel').forEach((panel) => {
            const h2 = panel.querySelector('h2');
            if (!h2) return;
            const key = panelTitles[h2.textContent.trim()];
            if (!key) return;
            const block = rect(panel);
            if (block) sections[key] = block;
        });
    }

    const bankRoot = document.querySelector('.dg-buchhaltung-bankabgleich');
    if (bankRoot) {
        bankRoot.querySelectorAll('section.dg-panel').forEach((panel) => {
            const h2 = panel.querySelector('h2');
            if (!h2) return;
            const t = (h2.textContent || '').trim();
            let key = '';
            if (/CAMT/i.test(t)) key = 'camt';
            else if (/MT940/i.test(t)) key = 'mt940';
            else if (/Geister/i.test(t)) key = 'geister';
            else if (/Offene Umsätze/i.test(t)) key = 'offen';
            else if (/Zugeordnet/i.test(t)) key = 'zugeordnet';
            if (!key) return;
            const block = rect(panel);
            if (block) sections[key] = block;
        });
    }

    const filterPanel = document.querySelector('.dg-ledger-filters');
    if (filterPanel) {
        const block = rect(filterPanel);
        if (block) sections['filter'] = block;
    }
    const summaryPanel = document.querySelector('.dg-opos-summary');
    if (summaryPanel) {
        const block = rect(summaryPanel);
        if (block) sections['summary'] = block;
    }
    const cashRoot = document.querySelector('.dg-buchhaltung-kassenbuch');
    if (cashRoot) {
        cashRoot.querySelectorAll('section.dg-panel').forEach((panel) => {
            const h2 = panel.querySelector('h2');
            const t = (h2?.textContent || '').trim();
            let key = '';
            if (/Tagesabschluss/i.test(t)) key = 'tagesabschluss';
            if (!key && panel.querySelector('.dg-table')) key = 'liste';
            if (!key) return;
            const block = rect(panel);
            if (block) sections[key] = block;
        });
    }
    const oposRoot = document.querySelector('.dg-buchhaltung-opos');
    if (oposRoot) {
        oposRoot.querySelectorAll('section.dg-panel').forEach((panel) => {
            if (!panel.querySelector('.dg-table')) return;
            const block = rect(panel);
            if (block) sections['liste'] = block;
        });
    }
    const guvRoot = document.querySelector('.dg-buchhaltung-auswertungen');
    if (guvRoot) {
        guvRoot.querySelectorAll('section.dg-panel').forEach((panel) => {
            const h2 = panel.querySelector('h2');
            const t = (h2?.textContent || '').trim();
            if (/Gewinn|Bilanz/i.test(t) || panel.querySelector('.dg-table')) {
                const block = rect(panel);
                if (block) sections['report'] = block;
            }
        });
    }
    const datevRoot = document.querySelector('.dg-buchhaltung-steuerberater-export');
    if (datevRoot) {
        datevRoot.querySelectorAll('section.dg-panel').forEach((panel) => {
            const h2 = panel.querySelector('h2');
            const t = (h2?.textContent || '').trim();
            let key = '';
            if (/Buchungsstapel/i.test(t)) key = 'stapel';
            else if (/Stammdaten/i.test(t)) key = 'stammdaten';
            if (!key) return;
            const block = rect(panel);
            if (block) sections[key] = block;
        });
    }
    const statsWrap = document.querySelector('.dg-wrap');
    if (statsWrap && document.querySelector('.dg-page-title')?.textContent?.includes('Statistik')) {
        const toolbar = document.querySelector('.dg-page-header__actions, .dg-toolbar');
        if (toolbar) {
            const block = rect(toolbar);
            if (block) sections['zeitraum'] = block;
        }
        const grids = document.querySelectorAll('.dg-form-grid');
        grids.forEach((grid) => {
            if (grid.querySelectorAll('.dg-panel').length >= 3) {
                const block = rect(grid);
                if (block) sections['kennzahlen'] = block;
            }
        });
        const panels = Array.from(document.querySelectorAll('section.dg-panel'));
        if (panels.length) {
            let unionRect = null;
            panels.slice(1).forEach((panel) => {
                unionRect = union(unionRect, rect(panel));
            });
            if (unionRect) sections['listen'] = unionRect;
        }
        const ga = panels.find((p) => /Google Analytics/i.test(p.querySelector('h2')?.textContent || ''));
        if (ga) {
            const block = rect(ga);
            if (block) sections['google'] = block;
        }
    }

    return sections;
}"""

FIELD_JS = """(selector) => {
    const scrollX = window.scrollX;
    const scrollY = window.scrollY;
    const el = document.querySelector(selector);
    if (!el) return null;
    const label = el.closest('label');
    const target = label || el;
    const r = target.getBoundingClientRect();
    if (r.width < 2 || r.height < 2) return null;
    const labelText = label?.querySelector('span')?.textContent?.trim()
        || el.getAttribute('name') || selector;
    const fixed = window.getComputedStyle(target).position === 'fixed'
        || window.getComputedStyle(el).position === 'fixed';
    return {
        x: Math.round(fixed ? r.left : r.left + scrollX),
        y: Math.round(fixed ? r.top : r.top + scrollY),
        w: Math.round(r.width),
        h: Math.round(r.height),
        label: labelText,
    };
}"""


def inject_cleanup(page) -> None:
    page.evaluate(
        """() => {
            const el = document.getElementById('dg-cookie-consent');
            if (el) el.remove();
            document.querySelectorAll('.dg-cc-overlay, .dg-cc-banner').forEach((n) => n.remove());
            const style = document.createElement('style');
            style.textContent = `
                body.dg-app { height: auto !important; min-height: auto !important; overflow: visible !important; }
                .dg-shell { overflow: visible !important; min-height: auto !important; }
                .dg-content { overflow: visible !important; height: auto !important; min-height: auto !important; }
                #dg-cookie-consent, .dg-cc-overlay { display: none !important; visibility: hidden !important; }
            `;
            document.head.appendChild(style);
            window.scrollTo(0, 0);
        }"""
    )


def parse_field_specs(argv: list[str]) -> dict[str, str]:
    out: dict[str, str] = {}
    for arg in argv:
        if "=" not in arg:
            continue
        key, sel = arg.split("=", 1)
        if key and sel:
            out[key.strip()] = sel.strip()
    return out


def prepare_page_state(page, html: Path) -> None:
    """Akademie-Capture: feste Overlays (z. B. Kichel) vor Screenshot öffnen."""
    if "kichel-dashboard-open" in html.name:
        page.evaluate(
            """() => {
                const panel = document.getElementById('dg-kichel-panel');
                const fab = document.querySelector('[data-kichel-fab]');
                if (panel) panel.hidden = false;
                if (fab) fab.setAttribute('aria-expanded', 'true');
            }"""
        )
        page.wait_for_timeout(400)
    if "akademie-modul-player" in html.name:
        page.evaluate(
            """() => {
                const msg = document.getElementById('dg-academy-player-message');
                if (msg) { msg.hidden = true; msg.textContent = ''; }
                document.querySelectorAll('.dg-scan-result').forEach((el) => {
                    if ((el.textContent || '').toLowerCase().includes('failed')) {
                        el.hidden = true;
                        el.textContent = '';
                    }
                });
                const video = document.getElementById('dg-academy-video');
                if (video) {
                    video.removeAttribute('src');
                    video.querySelectorAll('source').forEach((s) => s.remove());
                    video.poster = '';
                    video.style.background = '#1a1a1a';
                    video.style.minHeight = '320px';
                }
            }"""
        )
        page.wait_for_timeout(200)

    # Media-Demo: lokale PNGs in Vorschau (ohne Login-Preview)
    media_preview_map = {
        "media-edit-demo": "media-demo-becher.png",
        "media-crop-modal": "media-demo-becher.png",
        "media-edit-crop": "media-demo-becher-crop.png",
        "media-edit-frei": "media-demo-becher-frei.png",
        "media-list-demo": "media-demo-becher.png",
    }
    for key, filename in media_preview_map.items():
        if key not in html.name:
            continue
        demo = (ROOT / "storage/media/training/media/demo" / filename).resolve()
        if not demo.is_file():
            break
        uri = demo.as_uri()
        page.evaluate(
            """(uri) => {
                const img = document.getElementById('dg-media-preview');
                if (img) {
                    img.src = uri;
                    img.hidden = false;
                    img.classList.remove('dg-media-preview--empty');
                }
                document.querySelectorAll('.dg-media-thumb img, .dg-table img').forEach((el) => {
                    if (el && el.tagName === 'IMG') el.src = uri;
                });
            }""",
            uri,
        )
        page.wait_for_timeout(400)
        if "media-crop-modal" in html.name:
            page.evaluate(
                """(uri) => {
                    const modal = document.getElementById('dg-media-crop-modal');
                    const cropImg = document.getElementById('dg-media-crop-image');
                    if (modal) {
                        modal.hidden = false;
                        modal.setAttribute('aria-hidden', 'false');
                    }
                    if (cropImg) cropImg.src = uri;
                }""",
                uri,
            )
            page.wait_for_timeout(500)
        break


def capture(
    html: Path,
    png: Path,
    regions: Path | None = None,
    field_specs: dict[str, str] | None = None,
    *,
    viewport_only: bool = False,
) -> None:
    png.parent.mkdir(parents=True, exist_ok=True)
    with sync_playwright() as p:
        browser = p.chromium.launch(headless=True)
        page = browser.new_page(viewport={"width": VIEWPORT_W, "height": 1080})
        page.goto(html.as_uri(), wait_until="networkidle", timeout=120_000)
        page.wait_for_timeout(800)
        inject_cleanup(page)
        page.wait_for_timeout(300)
        prepare_page_state(page, html)

        # Regionen vor Viewport-Resize (fixed FAB/Panel sonst verschoben → Fokus ins Leere)
        field_map: dict[str, dict] = {}
        if field_specs:
            for key, selector in field_specs.items():
                data = page.evaluate(FIELD_JS, selector)
                if data:
                    field_map[key] = data

        section_map: dict[str, dict] = page.evaluate(SECTION_JS)

        if viewport_only:
            page.screenshot(path=str(png), full_page=False)
            img_h = 1080
        else:
            page.screenshot(path=str(png), full_page=True)
            img_h = Image.open(png).size[1]
            page.set_viewport_size({"width": VIEWPORT_W, "height": img_h})
            page.wait_for_timeout(200)

        if regions:
            regions.write_text(
                json.dumps(
                    {"width": VIEWPORT_W, "height": img_h, "sections": section_map, "fields": field_map},
                    ensure_ascii=False,
                    indent=2,
                ),
                encoding="utf-8",
            )
        browser.close()

    print(f"Screenshot: {png} ({Image.open(png).size[1]}px)")
    if regions and regions.is_file():
        payload = json.loads(regions.read_text(encoding="utf-8"))
        print(f"Abschnitte: {len(payload.get('sections', {}))}, Felder: {len(payload.get('fields', {}))} -> {regions}")


def main() -> int:
    args = [a for a in sys.argv[1:] if a != "--viewport-only"]
    viewport_only = len(args) < len(sys.argv) - 1
    if len(args) < 2:
        print(
            "Usage: academy-capture-page.py [--viewport-only] input.html output.png [regions.json] [key=selector ...]",
            file=sys.stderr,
        )
        return 1
    html = Path(args[0])
    png = Path(args[1])
    regions = Path(args[2]) if len(args) > 2 and args[2].endswith(".json") else None
    spec_start = 3 if regions else 2
    field_specs = parse_field_specs(args[spec_start:])
    if not html.is_file():
        print(f"Fehlt: {html}", file=sys.stderr)
        return 1
    capture(html, png, regions, field_specs or None, viewport_only=viewport_only)
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
