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
5. **Produktion:** Szenario zuerst · Demo-Daten · Störfaktoren weg · kurze Clips · VTT Pflicht · bei UI-Änderung neu aufnehmen.

---

## 0. Workflow (Reihenfolge)

| Schritt | Was |
|---------|-----|
| 1 | **Szenario** — `docs/akademie/szenario-{thema}.md` (Ablauf, Text grob, Dauer-Ziel) |
| 2 | **Sprechtext** — `docs/akademie/locales/{sprache}/{video-slug}.json` |
| 3 | **Screenshot** — echtes CRM (Export + Capture oder Screen-Recording) |
| 4 | **Render** — Video + VTT erzeugen |
| 5 | **Prüfen** — Checkliste unten; **Vollvideo** ansehen (nicht nur Kurz-Vorschau) |
| 6 | **Deploy** — MP4/VTT auf Instanz, `duration_sec` in DB |

Kein Render ohne fertiges Szenario und JSON — spart doppelte Arbeit.

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

**Vor der Aufnahme**

- Cookie-Banner, Support-Hinweise, leere Fehlermeldungen und Pop-ups **entfernen**
- **Datenschutz:** keine echten Kundennamen, Beträge, IBAN oder E-Mails — nur Demo-Daten oder Unkenntlichmachung
- **Aufnahme-Instanz:** Master/Live-Test (`dg.ganz-om.de`, `ganz-soft.de`) mit **Admin-User**, der alle für das Video nötigen Module zeigt
- **Konsistenz:** dieselbe Instanz und Rolle für zusammengehörige Clips einer Serie

**Sichtbare Kacheln:** Was ein Nutzer im CRM sieht, hängt von **Rolle und Abteilung** ab. Wenn das relevant ist (z. B. Dashboard-Überblick), im Intro **ein Satz** dazu — sonst wundern sich Anwender über „fehlende“ Kacheln.

**Nach CRM-UI-Änderungen:** Layout, Menü, Icons oder Kacheltext geändert → Screenshot und Video **neu erzeugen**. Altes Material nicht weiterverwenden.

**Technik (Dashboard):**

```bash
bash bin/academy-build-dashboard-video.sh
# Einzelschritte:
php bin/academy-export-dashboard-html.php --base=https://ganz-soft.de/
python3 bin/academy-capture-dashboard.py
python3 bin/academy-generate-dashboard-video.py --locale de
```

Für **andere Module:** gleiches Prinzip — echter Bildschirm (Playwright, Recording), dann schneiden/zoomen; **nie** UI neu zeichnen.

---

## 3. Sprache und Textstil

Zielgruppe: **Mitarbeiter ohne Fachkenntnisse** — weder Programmierer noch Buchhalter.

| So | Nicht so |
|----|----------|
| Kurze Sätze, **eine Idee pro Satz** | Fachjargon ohne Erklärung (GoBD, OPOS, CAMT …) |
| Alltagswörter: „Beleg“, „Kunde“, „Termin“ | „Datensatz“, „Entität“, „Modul“ |
| **Was** tun und **warum** — aus Nutzersicht | Technik, Datenbank, Code |
| Fachbegriff **kurz erklären** oder vermeiden | Abkürzungen voraussetzen |

**Ton:** ruhig, sachlich, freundlich — wie eine geduldige Kollegin; **nicht hetzen**, lieber kurze Pause als gedrängelter Text.

**Untertitel (VTT):** **Pflicht** zu jedem Clip — identisch zur gesprochenen Spur (Barrierefreiheit, Nutzung ohne Ton). Kein Video ohne `.vtt` veröffentlichen.

**Kurz halten:** **ein Thema pro Clip**; lieber mehrere kurze Videos (Richtwert **2–5 Minuten** pro Formular-Abschnitt) als ein langer Sammelband. Keine Mindestlänge pro Kachel — nur so lang wie nötig. Ausnahme: bewusst geplanter Überblick (z. B. alle Dashboard-Kacheln).

**Abkürzungen und Akronyme im Sprechtext**

| Regel | Beispiel |
|-------|----------|
| Im **gesprochenen Text** (und damit im **VTT**) Abkürzungen **ausschreiben** — nicht buchstabieren | Postleitzahl · Umsatzsteueridentifikationsnummer · Sozialversicherungsnummer |
| **Nicht** als Einzelbuchstaben vorlesen, wenn es ein deutsches Wort/Kürzel ist | nicht „P L Z“, nicht „U S t“ für Umsatzsteuer |
| Im **CRM-Bild** bleiben die Original-Labels (z. B. „PLZ“, „USt-IdNr.“) | Bild unverändert, Ton ausgeschrieben |
| **Ausnahme:** etablierte **Zweibuchstaben-Codes** im Formular, die so gemeint sind | Ländercode: „D E“ für Deutschland |

**Zoom bei Formular-Feldern (Pflicht)**

Pro Feld **drei Schritte** — damit Orientierung und Lesbarkeit erhalten bleiben:

1. **Ganzes Formular** (Überblick)
2. **Zoom auf Abschnitt** (z. B. Stamm, Kunde / Lieferant, Adresse)
3. **Zoom auf Feld** — **Label und Eingabe vollständig** sichtbar (breite Felder: weniger zoomen, nicht abschneiden)

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

## 6. Ergänzende Pflichten (Produktion & Pflege)

Zusammenfassung der Regeln, die über Navigation, Bild und Sprache hinausgehen:

| Thema | Regel |
|-------|--------|
| **Szenario zuerst** | Kein Aufnahme-/Render-Start ohne `szenario-*.md` + `locales/…/*.json` |
| **Datenschutz** | Nur Demo-Daten im Bild; keine echten Personen- oder Geschäftsdaten |
| **Störfaktoren** | Banner, Cookies, Fehlermeldungen vor Capture entfernen |
| **Ein Thema pro Video** | Kurze Clips; Überblicke als Ausnahme dokumentieren |
| **Aufnahme-Umgebung** | Live-Test/Master, Admin-User, alle benötigten Module sichtbar |
| **Rollen-Hinweis** | Optional im Intro, wenn sichtbare Module von Rolle/Abteilung abhängen |
| **UI-Änderungen** | Nach Layout-/Menü-Update Material neu erzeugen |
| **Untertitel** | VTT Pflicht, Text = gesprochene Spur |
| **Stimme** | DE: `de-DE-KatjaNeural`; andere Sprachen in Locale-JSON |
| **Demos** | Kurz-Vorschau ≠ Vollvideo — bei Vorschau **Gesamtdauer** nennen |
| **Deploy** | MP4 + VTT hochladen, `duration_sec` aktualisieren, **Vollvideo** geprüft |

---

## Checkliste vor Veröffentlichung

- [ ] Szenario + Locale-JSON **vor** Produktion fertig
- [ ] Video startet am **Dashboard** (oder dokumentierte Ausnahme)
- [ ] Navigation zum Thema **sichtbar**
- [ ] **Echte CRM-Screenshots** (Sidebar, Logo, Icons erkennbar)
- [ ] Keine Textfolien ohne UI
- [ ] Aufnahme ohne Cookie-Banner / Störmeldungen
- [ ] Keine personenbezogenen Live-Daten im Bild
- [ ] Text für **Nicht-Buchhalter** verständlich
- [ ] **Abkürzungen ausgeschrieben** im Sprechtext/VTT (Ausnahmen: z. B. Ländercode D E)
- [ ] Formular-Felder: Zoom **Formular → Abschnitt → Feld** (Label sichtbar)
- [ ] **Ein Thema** pro Clip (oder Überblick bewusst ausgewiesen)
- [ ] Stimme **Katja** (DE) bzw. eingetragene Locale-Stimme
- [ ] Untertitel (`.vtt`) **Pflicht** — liegt bei, Text = Audio
- [ ] **Vollvideo** geprüft (nicht nur Kurz-Vorschau)
- [ ] Dauer in `dg_academy_modules.duration_sec` stimmt
- [ ] Demo-Vorschau ≠ Vollvideo (Gesamtdauer angeben)

---

## Referenz für Agent-Chats

> **Akademie-Videos:** [`docs/akademie/VIDEO-REGELN.md`](VIDEO-REGELN.md) — Dashboard-Start, echte CRM-Bilder, einfache Sprache, Stimme Katja, Szenario-first, VTT Pflicht, Demo-Daten, bei UI-Änderung neu aufnehmen. Texte: [`locales/`](locales/).
