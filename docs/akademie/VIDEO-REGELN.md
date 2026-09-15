# Akademie — Regeln für Schulungsvideos

> **Verbindlich für alle Video-Clips** der CRM-Akademie (Erklärvideos, Modul-Einführungen, Kurzüberblicke).  
> **Technik (Dashboard-Beispiel):** [`bin/academy-build-dashboard-video.sh`](../bin/academy-build-dashboard-video.sh) · Szenario-Vorlage: [`szenario-dashboard-ueberblick.md`](szenario-dashboard-ueberblick.md)

Stand: 2026-09-15

---

## Kurzfassung (für Chats)

1. **Immer vom Dashboard starten** — Nutzer soll sehen, *wo* er im CRM ist und *wie* er dorthin kommt.  
2. **Nur echtes CRM-Bild** — keine nachgebauten Kacheln, keine erfundenen Folien, keine Mockups.  
3. **Einfache Sprache** — für Anwender ohne IT- und ohne Buchführungs-Vorkenntnisse.

---

## 1. Navigation: Dashboard als Startpunkt

Jedes Schulungsvideo beginnt mit dem **Dashboard** (Startseite nach Login).

| Pflicht | Begründung |
|---------|------------|
| Erste Bilder zeigen das **volle Dashboard** (Kacheln + Seitenmenü erkennbar) | Orientierung: „Das ist mein Einstieg ins Programm.“ |
| Danach **sichtbar zur Ziel-Stelle navigieren** (Kachel anklicken / Menü / Modul öffnen) | Nutzer lernt den **Weg**, nicht nur das Ziel. |
| Kein „Sprung“ mitten in eine Maske ohne vorherigen Kontext | Sonst wirkt es wie ein fremdes Programm. |

**Beispiel Ablauf (Modul-Video):**  
Dashboard (Gesamtüberblick) → Kachel oder Menüpunkt → Zielmodul → konkrete Funktion erklären.

**Ausnahme:** Reine Dashboard-Übersichtsvideos (z. B. „Kurzüberblick aller Kacheln“) bleiben auf dem Dashboard — dort ist kein weiterer Navigationsschritt nötig.

---

## 2. Bildmaterial: nur echte CRM-Ausschnitte

Alles, was im Video zu sehen ist, muss **1:1 aus dem laufenden CRM** stammen.

| Erlaubt | Nicht erlaubt |
|---------|----------------|
| Screenshot / Screen-Recording vom **Master oder Live-Test** (`dg.ganz-om.de`, `ganz-soft.de`) | Selbst gezeichnete Kacheln, PIL-/Canvas-Nachbauten |
| HTML-Export + Browser-Screenshot (echtes Layout, echte Icons, echtes CSS) | Dunkle Vollbild-Folien mit Text statt UI |
| Leichte Kamera-Zooms / Fokus-Rahmen **auf dem echten Bild** | Fantasie-UI, Stock-Fotos, generische „Software“-Grafiken |
| Unscharfe Abdunkelung **neben** dem Fokus (Nachbarkacheln bleiben erkennbar) | Vollflächige Overlays, die das CRM verdecken |

**Technischer Standard (Dashboard & statische Clips):**

```bash
# 1. HTML vom Live-CRM exportieren
php bin/academy-export-dashboard-html.php --base=https://ganz-soft.de/

# 2. Echter Screenshot + Kachel-Koordinaten
python3 bin/academy-capture-dashboard.py

# 3. Video mit Stimme + Untertiteln
python3 bin/academy-generate-dashboard-video.py
# oder alles zusammen:
bash bin/academy-build-dashboard-video.sh
```

Für **andere Module** gilt dasselbe Prinzip: zuerst echten Bildschirm erfassen (Playwright, Screen-Recording), dann schneiden/zoomen — **nie** UI neu zeichnen.

---

## 3. Sprache und Textstil

Zielgruppe: **Mitarbeiter und Anwender ohne Fachkenntnisse** — keine Programmierer, oft auch keine Buchhalter.

| So schreiben / sprechen | So nicht |
|-------------------------|----------|
| Kurze Sätze, **eine Idee pro Satz** | Fachjargon (GoBD, OPOS, CAMT, Journal, API …) ohne Erklärung |
| **Alltagswörter:** „Beleg“, „Rechnung“, „Kunde“, „Termin“ | „Datensatz“, „Entität“, „Modul instanziieren“ |
| Sage **was** der Nutzer hier macht und **warum** es ihm hilft | Technische Implementierung, Datenbank, Code |
| „Hier sehen Sie …“ / „Hier tragen Sie … ein“ | Passiv und behördlich |
| Bei Fachbegriff **einmal kurz erklären** oder vermeiden | Abkürzungen voraussetzen (BWA, SuSa, UStVA) — im Video aussprechen und in 3–5 Wörtern erklären |

**Ton:** ruhig, sachlich, freundlich — wie eine geduldige Kollegin, nicht wie ein Handbuch.

**Untertitel (VTT):** derselbe Text wie die gesprochene Spur; keine abweichende Fachsprache.

**Orientierung an bestehenden Szenarien:** [`docs/akademie/szenario-*.md`](.) — vor neuer Produktion Szenario anlegen oder erweitern.

---

## Checkliste vor Veröffentlichung

- [ ] Video startet am **Dashboard** (oder dokumentierte Ausnahme)
- [ ] Navigation zum Thema ist **im Video sichtbar**
- [ ] Alle gezeigten UI-Teile sind **echte CRM-Screenshots** (prüfbar an Sidebar, Logo, Schrift, Icons)
- [ ] Keine Textfolien ohne UI
- [ ] Text verständlich für **Nicht-Buchhalter** gelesen
- [ ] Untertitel (`.vtt`) liegt bei
- [ ] Dauer in `dg_academy_modules.duration_sec` stimmt
- [ ] Vorschau ≠ Vollvideo: bei Demos **gesamtes Video** oder Dauer angeben

---

## Referenz für Agent-Chats

In Cursor / Cloud-Agent am Session-Start oder vor Video-Arbeit:

> **Akademie-Videos:** [`docs/akademie/VIDEO-REGELN.md`](VIDEO-REGELN.md) — Dashboard-Start, nur echte CRM-Bilder, einfache Sprache.
