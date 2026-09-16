# Akademie — Texte & Sprachen (Vorbereitung Mehrsprachigkeit)

Stand: 2026-09-15 · **Noch keine CRM-UI-Übersetzung** — diese Struktur bereitet Videos und spätere CRM-i18n vor.

> Regeln: [`../VIDEO-REGELN.md`](../VIDEO-REGELN.md)

---

## Prinzip

| Schicht | Sprache | Ort |
|---------|---------|-----|
| **Gesprochener Text + Untertitel** | pro Sprache | `docs/akademie/locales/{locale}/{video-slug}.json` |
| **Bild / Screenshot** | CRM-Oberflächensprache des Nutzers | neu aufnehmen, wenn CRM übersetzt ist |
| **Video-Datei** | pro Sprache | `storage/media/training/…/{video-slug}.{locale}.mp4` |
| **Untertitel-Datei** | pro Sprache | `{video-slug}.{locale}.vtt` |

**Wichtig:** Audio kann früher mehrsprachig werden als die CRM-Oberfläche. Sobald das CRM mehrsprachig ist, Screenshots **pro Sprache** neu erfassen (Kacheltexte, Menüs).

---

## Dateiformat `{video-slug}.json`

```json
{
  "video_slug": "dashboard-ueberblick",
  "locale": "de",
  "title": "…",
  "voice": "de-DE-KatjaNeural",
  "intro": "…",
  "segments": {
    "kontakte": "…",
    "terminkalender": "…"
  }
}
```

- **`segments`-Keys** = CRM-Modul-Slug (stabil, sprachunabhängig) — **nicht** der deutsche Kacheltitel.
- **`voice`** = edge-tts-Stimme für diese Sprache (Deutsch: fest `de-DE-KatjaNeural`, siehe VIDEO-REGELN.md).
- Neue Sprache = neues JSON unter `locales/en/`, `locales/fr/`, … — **Video neu rendern**, Bild ggf. separat.

---

## Dateinamen (Medien)

| Sprache | MP4 | VTT | Hinweis |
|---------|-----|-----|---------|
| Deutsch (Standard) | `dashboard-ueberblick.mp4` | `dashboard-ueberblick.vtt` | Abwärtskompatibel zu bestehenden Modulen |
| Weitere | `dashboard-ueberblick.en.mp4` | `dashboard-ueberblick.en.vtt` | Suffix `.xx` (ISO 639-1) |

Später in der DB: Modul pro Sprache oder `locale`-Feld — **noch nicht implementiert**.

---

## Workflow neues Video

1. Szenario: `docs/akademie/szenario-{thema}.md` (Deutsch zuerst)
2. Text: `docs/akademie/locales/de/{video-slug}.json`
3. Screenshot vom CRM (Sprache der UI beachten)
4. Render: `python3 bin/academy-generate-dashboard-video.py --locale de` (bzw. modulspezifisches Skript)
5. Übersetzung später: JSON kopieren → `locales/en/` → übersetzen → gleiches Bild oder neuer Screenshot → erneut rendern

---

## Anbindung CRM-Übersetzung (später)

Wenn CRM-UI mehrsprachig wird:

- Dieselben **Modul-Slugs** in Akademie-JSON und CRM-Menü verwenden.
- Keine deutschen Labels als Schlüssel in Code oder JSON.
- Optional später: Texte aus CRM-Übersetzungstabelle exportieren — JSON-Struktur bleibt gleich.
