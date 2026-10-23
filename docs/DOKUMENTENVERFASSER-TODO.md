# Dokumentenverfasser — Plan & ToDo

Stand: **2026-10-23** (Entscheidungen bestätigt)

**Entscheidung:** Eigener CRM-Code (kein Fremd-Office-Suite-Embed). Briefpapier + Editor + Export. Platzhalter-Seite `/app?page=dokumentenverfasser` existiert bereits unter Kommunikation.

**Festgelegt:**

| Thema | Entscheidung |
|-------|----------------|
| Editor | **TipTap** (ProseMirror) — Absätze, Tabellen, Emoji, Lucide, Mediathek |
| Briefinhalt | **DIN-5008-Zonen** + **gesetzliche Pflichtangaben** + **empfohlene** Angaben (alle umsetzen; Pflicht = Warnung wenn leer, empfohlen = Standard an, abschaltbar) |
| Text-Assist | lokal / optional LanguageTool; Cloud (Google & Co.) **kein Standard**, höchstens später opt-in, nie Zwang |

**Rechts-/Norm-Hinweis (Fachrolle RA/StB/Prüfer):**

| Ebene | Was | Verbindlichkeit |
|-------|-----|-----------------|
| **DIN 5008** | Layout: Briefkopf, Anschriftfenster (Fensterbrief), Infoblock, Betreff, Ränder, Abstände | **Empfehlung / Best Practice**, keine gesetzliche Pflicht |
| **Gesetz** | Pflichtangaben auf Geschäftsbriefen „gleichviel welcher Form“ (auch E-Mail) | **Pflicht** — je Rechtsform (z. B. § 35a GmbHG, § 37a HGB, § 80 AktG) |
| **UStG § 14** | Steuernummer / USt-IdNr. | Pflicht auf **Rechnungen**, auf freien Briefen empfohlen, nicht automatisch Brief-Pflicht |

DIN 5008 und Gesetz ergänzen sich: DIN sagt *wo*, Gesetz sagt *was mindestens*.

---

## 1. Briefpapier / Schreibendesign (Einstellungen)

Neuer Settings-Tab z. B. **Einstellungen → Dokumente / Briefpapier** (eigener Tab, Daten aus Firma wiederverwenden, nicht doppelpflegen wo möglich).

### 1.1 Pflicht- und Empfohlen-Felder (Datenquellen)

**Gesetzlich typisch (GmbH / UG — § 35a GmbHG):**

- vollständige Firma (wie Register)
- Rechtsform
- Sitz
- Registergericht + HRB/HRA-Nr.
- alle Geschäftsführer (Nachname + mind. ein ausgeschriebener Vorname)
- ggf. Aufsichtsratsvorsitzender
- bei Kapitalangaben: Stammkapital (+ ausstehende Einlagen)

**Weitere Rechtsformen analog** (e. K., AG, OHG/KG, GbR) — Auswahl nach Firmen-Rechtsform in Einstellungen → Firma.

**Empfohlen / praxisüblich (Fuß oder Kopf):**

- Anschrift, Telefon, E-Mail, Website
- Bankverbindung (IBAN/BIC)
- Steuernummer und/oder USt-IdNr. (für Rechnungen ohnehin; auf Briefen sinnvoll)
- Links: Impressum, Datenschutz, AGB, Widerrufsbelehrung (Website-URLs aus CRM/Website-Einstellungen)

**DIN-5008-Zonen (Positionen einstellbar):**

| Zone | Inhalt | Einstellbar |
|------|--------|-------------|
| Briefkopf | Logo, Firmenname, Kurz-Kontakt | Position Logo (L/M/R), Größe Logo, Sichtbarkeit |
| Absenderzeile (Futter / Fenster) | Absender klein über Empfänger | an/aus, Schriftgröße |
| Anschriftenfeld | Empfänger (DIN Fenster: ca. 85×45 mm, 20 mm vom linken Rand) | Raster-Hilfe anzeigen |
| Infoblock (rechts) | Ihr Zeichen, Ihre Nachricht vom, Unser Zeichen, Ansprechpartner, Telefon, E-Mail, Datum, Bearbeitungsnr. | je Feld: an/aus, Reihenfolge, Label |
| Betreff | ohne Wort „Betreff“ | Stil (fett) |
| Fußzeile | Pflichtangaben + Bank + Links | Spalten, Schriftgröße, Trennlinie |
| Hintergrund | Farbe / Bild / Wasserzeichen | Deckkraft, Kachel/Strecken, nur 1. Seite oder alle |

**Korrespondenz-Felder (je Dokument oder Vorlage):**

- *Ihre Seite:* Ihr Schreiben vom, Ihre Bearbeitungsnummer, Ihr Ansprechpartner  
- *Unsere Seite:* Ansprechpartner, Abteilung, Bearbeitungsnummer, Durchwahl, E-Mail  

Alles: **anzeigen/verstecken**, Größe, Position/Reihenfolge.

### 1.2 Wiederverwendung bestehender CRM-Daten

- `CompanySettings` / erweiterte Firma (Adresse, Telefon, Web)
- Logo aus Mediathek / Appearance
- Footer-URLs analog `EmailLayoutSettings` (Impressum, Datenschutz, AGB) + Widerruf
- Fehlende Register-/GF-Felder: in Firma- oder Briefpapier-Einstellungen **ergänzen**, nicht hardcodieren

### 1.3 ToDo — Briefpapier (B1–B4)

- [x] **B1** Datenmodell `LetterheadSettings` (`src/Documents/LetterheadSettings.php`, Store-Key `letterhead`, keine Extra-Tabelle) — DIN-Defaults, Infoblock, Fuß Pflicht/empfohlen, Export-Flags, `resolvedCompany()` / `missingMandatoryFields()`
- [x] **B2** Settings-UI: Tab **Einstellungen → Kommunikation → Briefpapier** (`tab-letterhead.php`) — Logo/Zonen/Infoblock/Fuß/Links/Hintergrund/Export; Soft-Hinweis Pflichtangaben beim Speichern
- [x] **B3** `complianceReport()` je Rechtsform (Basis §&nbsp;35a GmbHG / §&nbsp;37a HGB / …); Soft-Check; empfohlene Fußfelder an/Datenlücken; DIN-Zonen-Status + Button „DIN-Standardlayout wiederherstellen“
- [ ] **B4** Vorschau A4 (HTML/CSS mm-genau, DIN-Hilfslinien optional) — gleiche Renderer-Basis wie Export

---

## 2. Seite `dokumentenverfasser` — Inhalt & Editor

### 2.1 Aufbau der Arbeitsfläche

1. Briefpapier-Rahmen (alles aus Einstellungen, was aktiv ist) — read-only Rahmen  
2. Empfänger / Infoblock-Felder (editierbar pro Dokument)  
3. **Betreff**  
4. **Absätze:** Standard 1 Absatz; **+** weitere Absätze (Reihenfolge, Löschen)  
5. Anrede / Grußformel (Vorlagen wählbar)  
6. Export-Buttons (nur Formate, die in Einstellungen freigeschaltet sind)

### 2.2 Editor-Empfehlung (Entscheidung)

| Option | Pro | Contra |
|--------|-----|--------|
| TipTap (gewählt) | Tabellen, Marks, Embeds; JSON; Emoji/Lucide/Media | Aufbauaufwand akzeptiert |

**Umsetzung:** TipTap mit **CRM-Plugins**:

- Emoji (bestehende CRM-Emoji-Quellen / gleiche wie Website wo vorhanden)
- Lucide-Icons (wie Website/Kalender-Embed)
- Bild aus **Mediathek** (`MediaRepository`)
- Tabellen (TipTap Table)
- Absätze als strukturierte Blöcke (nicht ein einziges HTML-Chaos)

Speicherformat intern: **strukturiertes JSON** (Blöcke) + gerendertes HTML für PDF. Nicht nur „ein HTML-Blob“.

### 2.3 Rechtschreibung & Verständlichkeit

| Funktion | Machbarkeit | Empfehlung |
|----------|-------------|------------|
| Rechtschreibung Browser | `spellcheck` + Sprache DE | **sofort** (Basis) |
| Rechtschreibung Server | LanguageTool (self-host oder API) | **Phase 2** — DSGVO-freundlicher als Fremd-Cloud |
| „Schön geschrieben?“ / Verständlichkeit | Lesbarkeits-Heuristik lokal; optional später Cloud-KI | **Assist**, nie Pflicht |
| Cloud-KI (Google & Co. nur als Beispiel) | möglich, Datenabfluss | **kein Standard** — höchstens später opt-in |

**Fest für DG:**  
1) Browser-Spellcheck + optionale LanguageTool-Anbindung  
2) lokale Klartext-Empfehlungen (keine Cloud-Pflicht)  
3) Cloud-Assist erst später, explizit abschaltbar

### 2.4 Export-Formate (einstellbar, welche Buttons sichtbar)

| Format | Technik (Vorschlag) | Priorität |
|--------|---------------------|-----------|
| **PDF** | HTML→PDF (bestehende CRM-Druck-/PDF-Pipeline wo möglich, sonst Dompdf/ähnliches) | P0 |
| **.txt** | Plain aus Blöcken | P0 |
| **.md** | Markdown aus Blöcken | P1 |
| **Word (.docx)** | PHPWord o. Ä. | P1 |
| Excel | **nicht** Kern des Briefschreibers (eigener Track später) — Menütext heute noch „Excel“; im Plan bewusst trennen | später |

Einstellungen: Checkboxen „PDF / TXT / MD / DOCX anbieten“.

### 2.5 Speichern / GoBD

- Dokumente in DB + optional Dateiarchiv (`storage/…`)
- Metadaten: wer, wann, Kontaktbezug, Version
- Kein stilles Überschreiben ohne Version/Historie bei „final“
- Audit analog Post (`AuditLog`) bei Export/Finalisieren

### 2.6 ToDo — Editor & Export (D1–D7)

- [ ] **D1** Datenmodell Dokument (Betreff, Blöcke, Empfänger, Infoblock, Status Entwurf/Final)
- [ ] **D2** UI `page=dokumentenverfasser`: Liste + Neu/Bearbeiten, Briefpapier-Rahmen
- [ ] **D3** Editor: TipTap + Absätze (+), Tabellen, Emoji, Lucide, Mediathek
- [ ] **D4** Spellcheck + Klartext-Empfehlungen (lokal); LanguageTool optional
- [ ] **D5** Export-Pipeline PDF / TXT / MD / DOCX; Format-Schalter in Einstellungen
- [ ] **D6** Kontakt-/Vorgangsbezug (optional P5-ähnlich)
- [ ] **D7** Rechte (Modul `dokumentenverfasser`), Smoke, Deploy Master → Sync

---

## 3. Phasen-Reihenfolge

```
B1 → B2 → B4 (Briefpapier + Vorschau)
     ↓
D1 → D2 → D3 (Dokument + Editor)
     ↓
D5 (Export) → D4 (Assist) → D6 → D7
```

B3 (Pflichtangaben-Warnung) parallel zu B2.

**Nicht in v1:** Excel-Erzeugung, zeitgebundene Vertretung im Briefpapier, Google-Pflicht-Cloud-Check, fremde Office-Clouds.

---

## 4. Explizit nicht

- Roundcube/Office-365-Embed als Ersatz  
- Sondercode pro Domain — gleicher Code, Firmendaten aus Instanz-DB  
- Pflicht-Upload an Google zur Textanalyse  
- Beleg-PDF-Pipeline ersetzen (Buchhaltung bleibt getrennt; Briefpapier kann später Belege optisch angleichen)

---

## 5. Smoke (nach D7)

- [ ] Einstellungen Briefpapier: Logo Größe/Position, Fuß Pflichtangaben, Link Impressum
- [ ] Neues Schreiben: Betreff, 2 Absätze, Tabelle, Mediathek-Bild
- [ ] Export PDF + eines weiteren freigeschalteten Formats
- [ ] Nur-PDF-Instanz: keine Word-Buttons
- [ ] Deploy Master → `sync-crm-from-master.sh`
