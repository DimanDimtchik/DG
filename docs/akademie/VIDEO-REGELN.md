# Akademie — Regeln für Schulungsvideos

> **Verbindlich für alle Video-Clips** der CRM-Akademie (Erklärvideos, Modul-Einführungen, Kurzüberblicke).  
> **Texte / Sprachen:** [`locales/README.md`](locales/README.md) · **Technik:** [`bin/academy-build-dashboard-video.sh`](../bin/academy-build-dashboard-video.sh)

Stand: 2026-09-15

---

## Kurzfassung (für Chats)

1. **Immer vom Dashboard starten** — Nutzer soll sehen, *wo* er ist und *wie* er dorthin kommt.  
2. **Nur echtes CRM-Bild** — keine nachgebauten Kacheln, keine erfundenen Folien.  
3. **Einfache Sprache** — für Anwender ohne IT- und Buchführungs-Vorkenntnisse.  
4. **Feste Stimme (Deutsch):** `de-DE-KatjaNeural` (edge-tts) — alle Clips einheitlich.

---

## 1. Navigation: Dashboard als Startpunkt

Jedes Schulungsvideo beginnt mit dem **Dashboard** (Startseite nach Login).

| Pflicht | Begründung |
|---------|------------|
| Erste Bilder zeigen das **volle Dashboard** (Kacheln + Seitenmenü erkennbar) | Orientierung: „Das ist mein Einstieg ins Programm.“ |
| Danach **sichtbar zur Ziel-Stelle navigieren** (Kachel / Menü / Modul) | Nutzer lernt den **Weg**, nicht nur das Ziel. |
| Kein Sprung mitten in eine Maske ohne Kontext | Sonst wirkt es wie ein fremdes Programm. |

**Beispiel (Modul-Video):** Dashboard → Kachel/Menü → Zielmodul → Funktion erklären.

**Ausnahme:** Reine Dashboard-Übersicht (z. B. „Kurzüberblick aller Kacheln“) bleibt auf dem Dashboard.

---

## 2. Bildmaterial: nur echte CRM-Ausschnitte

Alles Sichtbare muss **1:1 aus dem laufenden CRM** stammen.

| Erlaubt | Nicht erlaubt |
|---------|----------------|
| Screenshot vom **Master oder Live-Test** | Selbst gezeichnete Kacheln, PIL-/Canvas-Nachbauten |
| HTML-Export + Browser-Screenshot (echtes CSS/Icons) | Dunkle Vollbild-Folien mit Text |
| Leichte Zooms / Fokus-Rahmen **auf dem echten Bild** | Stock-Fotos, Mockups, generische Software-Grafiken |
| Leichte Abdunkelung **neben** dem Fokus | Overlays, die das CRM verdecken |

**Vor der Aufnahme:** Cookie-Banner, Support-Hinweise und Störmeldungen entfernen. **Datenschutz:** keine echten Kundennamen, Beträge oder E-Mails — Demo-Daten oder Unkenntlichmachung.

**Technik (Dashboard):** `bin/academy-build-dashboard-video.sh`

---

## 3. Sprache und Textstil

Zielgruppe: **Mitarbeiter ohne Fachkenntnisse** — weder Programmierer noch Buchhalter.

| So | Nicht so |
|----|----------|
| Kurze Sätze, **eine Idee pro Satz** | Fachjargon ohne Erklärung (GoBD, OPOS, CAMT …) |
| Alltagswörter: „Beleg“, „Kunde“, „Termin“ | „Datensatz“, „Entität“, „Modul“ |
| **Was** tun und **warum** — aus Nutzersicht | Technik, Datenbank, Code |
| Fachbegriff **kurz erklären** oder vermeiden | Abkürzungen voraussetzen |

**Ton:** ruhig, sachlich, freundlich — wie eine geduldige Kollegin.

**Untertitel (VTT):** identisch zur gesprochenen Spur.

**Kurz halten:** ein Thema pro Clip; lieber mehrere kurze Videos als ein langer Sammelband. Keine Mindestlänge pro Kachel — nur so lang wie nötig.

**Szenario zuerst:** Text in `docs/akademie/szenario-*.md` und `docs/akademie/locales/{sprache}/*.json` **vor** Aufnahme/Render festlegen.

---

## 4. Stimme (einheitlich)

| Sprache | Stimme (edge-tts) | Status |
|---------|-------------------|--------|
| **Deutsch** | `de-DE-KatjaNeural` | **Verbindlich** für alle deutschen Clips |
| Weitere Sprachen | je Sprache in `locales/…/voice` eintragen | erst bei Übersetzung festlegen |

Abweichungen nur mit dokumentierter Begründung (z. B. barrierefreie Alternative).

---

## 5. Mehrsprachigkeit — jetzt vorbereiten, später ausbauen

**Noch keine Pflicht** — aber von Anfang an so arbeiten, dass Übersetzung später leicht wird:

| Heute | Später (mit CRM-i18n) |
|-------|------------------------|
| Texte in `docs/akademie/locales/de/{video-slug}.json` | Kopie nach `locales/en/`, … und übersetzen |
| Segment-Keys = **CRM-Modul-Slug** (sprachneutral) | Gleiche Slugs wie CRM-Menü |
| Deutsch: `video.mp4` + `video.vtt` | Weitere Sprachen: `video.en.mp4`, `video.en.vtt` |
| Screenshot deutsch (CRM-Oberfläche DE) | Pro UI-Sprache **neuer Screenshot**, wenn CRM übersetzt ist |

**Trennung:** Gesprochene Sprache ≠ Bildsprache möglich (z. B. englische Stimme über deutschem UI) — nur bewusst so dokumentieren. Ideal: Stimme und UI stimmen überein.

Details: [`locales/README.md`](locales/README.md)

---

## Checkliste vor Veröffentlichung

- [ ] Video startet am **Dashboard** (oder dokumentierte Ausnahme)
- [ ] Navigation zum Thema **sichtbar**
- [ ] **Echte CRM-Screenshots** (Sidebar, Logo, Icons erkennbar)
- [ ] Keine Textfolien ohne UI
- [ ] Text für **Nicht-Buchhalter** verständlich
- [ ] Stimme **Katja** (DE) bzw. eingetragene Locale-Stimme
- [ ] Untertitel (`.vtt`) liegt bei
- [ ] Keine personenbezogenen Live-Daten im Bild
- [ ] Dauer in `dg_academy_modules.duration_sec` stimmt
- [ ] Demo-Vorschau ≠ Vollvideo (Dauer angeben)

---

## Referenz für Agent-Chats

> **Akademie-Videos:** [`docs/akademie/VIDEO-REGELN.md`](VIDEO-REGELN.md) — Dashboard-Start, echte CRM-Bilder, einfache Sprache, Stimme Katja. Texte: [`locales/`](locales/).
