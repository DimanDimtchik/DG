#!/usr/bin/env python3
"""Build local Lucide stroke paths for WebsiteMenuIcons (no CDN)."""
from __future__ import annotations

import json
import os
import re
import subprocess
import sys
from datetime import datetime, timezone
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
OUT = ROOT / "src/Website/data/lucide-menu-icons.php"

LABELS_DE = {
    "house": "Start / Haus",
    "home": "Start / Haus",
    "calendar": "Kalender",
    "users": "Personen",
    "user": "Person",
    "mail": "E-Mail",
    "globe": "Website / Globus",
    "file-text": "Dokument",
    "menu": "Liste / Menü",
    "folder": "Ordner",
    "tag": "Etikett / Preis",
    "scale": "Rechtliches / Waage",
    "info": "Info",
    "image": "Bild",
    "images": "Bilder",
    "package": "Katalog / Paket",
    "calculator": "Buchhaltung",
    "receipt": "Beleg / Quittung",
    "settings": "Einstellungen",
    "palette": "Design / Farbe",
    "layout-grid": "Layout",
    "external-link": "Externer Link",
    "chevron-down": "Pfeil (Untermenü)",
    "phone": "Telefon",
    "map-pin": "Standort",
    "shopping-cart": "Shop / Warenkorb",
    "credit-card": "Zahlung",
    "heart": "Favorit",
    "star": "Stern / Bewertung",
    "search": "Suche",
    "download": "Download",
    "upload": "Upload",
    "link": "Link",
    "lock": "Gesperrt / Login",
    "unlock": "Freigeschaltet",
    "bell": "Benachrichtigung",
    "clock": "Uhr / Zeit",
    "building": "Firma / Gebäude",
    "briefcase": "Business",
    "book-open": "Handbuch",
    "help-circle": "Hilfe",
    "shield": "Sicherheit / Datenschutz",
    "truck": "Versand / Lieferung",
    "gift": "Geschenk",
    "percent": "Rabatt / Prozent",
    "euro": "Euro / Preis",
    "message-circle": "Nachricht / Chat",
    "video": "Video",
    "music": "Musik",
    "camera": "Kamera",
    "printer": "Drucken",
    "share-2": "Teilen",
    "rss": "Feed / Blog",
    "newspaper": "News / Presse",
    "graduation-cap": "Schulung / Bildung",
    "stethoscope": "Gesundheit",
    "wrench": "Service / Werkzeug",
    "hammer": "Handwerk",
    "leaf": "Nachhaltigkeit",
    "sun": "Sommer",
    "moon": "Nacht",
    "cloud": "Cloud",
    "wifi": "WLAN",
    "smartphone": "Mobil",
    "laptop": "Computer",
    "monitor": "Bildschirm",
    "database": "Datenbank",
    "code": "Code / Entwicklung",
    "bug": "Fehler / Support",
    "check-circle": "Erledigt / OK",
    "x-circle": "Abgebrochen",
    "alert-circle": "Warnung",
    "plus-circle": "Neu hinzufügen",
    "minus-circle": "Entfernen",
    "arrow-right": "Weiter / Pfeil",
    "arrow-left": "Zurück",
    "chevron-right": "Chevron rechts",
    "chevron-left": "Chevron links",
}

LEGACY_ALIASES = {
    "home": "house",
    "contacts": "users",
    "website": "globe",
    "document": "file-text",
    "nav": "menu",
    "catalog": "package",
    "accounting": "calculator",
    "images": "image",
    "layout": "layout-grid",
    "external": "external-link",
}


def ensure_lucide(lucide_dir: Path | None) -> Path:
    if lucide_dir and (lucide_dir / "dist/esm/icons").is_dir():
        return lucide_dir
    build = Path("/tmp/lucide-build")
    pkg = build / "package"
    icons = pkg / "dist/esm/icons"
    if not icons.is_dir():
        build.mkdir(parents=True, exist_ok=True)
        subprocess.run(["npm", "pack", "lucide@0.469.0"], cwd=build, check=True, capture_output=True)
        tgz = next(build.glob("lucide-*.tgz"))
        subprocess.run(["tar", "-xzf", str(tgz), "-C", str(build)], check=True)
    return pkg


def extract_paths(content: str) -> str:
    parts: list[str] = []
    for tag, attrs in re.findall(r'\[\s*"(path|circle|rect|line)"\s*,\s*\{([^}]*)\}\s*\]', content):
        if tag == "path":
            m = re.search(r'd:\s*"([^"]+)"', attrs)
            if m:
                parts.append(f'<path d="{m.group(1)}"/>')
        elif tag == "circle":
            m = re.search(
                r'cx:\s*"([^"]+)"[^}]*cy:\s*"([^"]+)"[^}]*r:\s*"([^"]+)"', attrs
            )
            if m:
                parts.append(f'<circle cx="{m.group(1)}" cy="{m.group(2)}" r="{m.group(3)}"/>')
        elif tag == "rect":
            amap = dict(re.findall(r'(\w+):\s*"([^"]+)"', attrs))
            rect = "<rect"
            for k in ("x", "y", "width", "height", "rx", "ry"):
                if k in amap:
                    rect += f' {k}="{amap[k]}"'
            parts.append(rect + "/>")
        elif tag == "line":
            amap = dict(re.findall(r'(\w+):\s*"([^"]+)"', attrs))
            if all(k in amap for k in ("x1", "y1", "x2", "y2")):
                parts.append(
                    f'<line x1="{amap["x1"]}" y1="{amap["y1"]}" x2="{amap["x2"]}" y2="{amap["y2"]}"/>'
                )
    return "".join(parts)


def human_label(icon_id: str) -> str:
    return " ".join(w.capitalize() for w in icon_id.split("-"))


def tags(icon_id: str, label: str) -> list[str]:
    raw = icon_id.split("-") + re.split(r"\s+/?\s*", label.lower())
    seen: set[str] = set()
    out: list[str] = []
    for t in raw:
        t = t.strip()
        if t and t not in seen:
            seen.add(t)
            out.append(t)
    return out


def php_export(value, indent: int = 0) -> str:
    pad = "    " * indent
    if value is None:
        return "null"
    if isinstance(value, bool):
        return "true" if value else "false"
    if isinstance(value, int):
        return str(value)
    if isinstance(value, float):
        return repr(value)
    if isinstance(value, str):
        return "'" + value.replace("\\", "\\\\").replace("'", "\\'") + "'"
    if isinstance(value, list):
        if not value:
            return "[]"
        inner = ",\n".join(pad + "    " + php_export(v, indent + 1) for v in value)
        return "[\n" + inner + ",\n" + pad + "]"
    if isinstance(value, dict):
        if not value:
            return "[]"
        inner = ",\n".join(
            pad + "    " + php_export(str(k), indent + 1) + " => " + php_export(v, indent + 1)
            for k, v in value.items()
        )
        return "[\n" + inner + ",\n" + pad + "]"
    return php_export(str(value), indent)


def main() -> int:
    lucide_arg = Path(sys.argv[1]) if len(sys.argv) > 1 else None
    lucide_dir = ensure_lucide(lucide_arg)
    icon_dir = lucide_dir / "dist/esm/icons"
    icons: dict[str, dict] = {}
    for file in sorted(icon_dir.glob("*.js")):
        if file.name.endswith(".map") or file.stem in ("index", "defaultAttributes"):
            continue
        content = file.read_text(encoding="utf-8", errors="ignore")
        paths = extract_paths(content)
        if not paths:
            continue
        icon_id = file.stem
        label = LABELS_DE.get(icon_id, human_label(icon_id))
        icons[icon_id] = {"label": label, "paths": paths, "tags": tags(icon_id, label)}

    OUT.parent.mkdir(parents=True, exist_ok=True)
    lines = [
        "<?php",
        "declare(strict_types=1);",
        "",
        "/** Auto-generated by bin/build-website-menu-lucide.py — do not edit. */",
        "return [",
        f"    'generated_at' => {php_export(datetime.now(timezone.utc).isoformat())},",
        "    'source' => 'lucide',",
        f"    'legacy_aliases' => {php_export(LEGACY_ALIASES)},",
        "    'icons' => [",
    ]
    for icon_id, meta in sorted(icons.items()):
        lines.append(f"        {php_export(icon_id)} => {php_export(meta)},")
    lines.extend(["    ],", "];", ""])
    OUT.write_text("\n".join(lines), encoding="utf-8")
    print(f"Wrote {len(icons)} icons to {OUT} ({OUT.stat().st_size} bytes)")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
