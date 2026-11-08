# Kontakt-Unterschrift (Brief/Mail) — tokenarm

> **Eine Chat-Session = eine Mini-ID.**  
> Ziel: Unterschrifts**bild** am Kontakt speichern und in Brief/Mail wiederverwenden.  
> **Kein QES**, kein Schriftform-Ersatz (§ 126a BGB) — nur einfache elektronische Signatur / Layout.

Stand: **2026-11-08** · Katalog: [`UI-KLASSENKATALOG.md`](UI-KLASSENKATALOG.md) · Recht Kurz: unten §Recht

---

## Warum tokenarm

| Regel | Bedeutung |
|-------|-----------|
| **1 ID = 1 kleiner Diff** | oft 1 Datei oder Migration + 1 View |
| **Kein QES-Anbieter** | kein Drittanbieter, keine Video-Ident |
| **Kein Tenant-Hardcode** | Daten nur Instanz-DB/Medien |
| **Katalog-UI** | keine Parallelwelt-CSS |
| **Guard** | bei View-Änderung `php bin/ui-form-spacing-selftest.php` |

**Chat-Prompt:**  
`Nur Mini-ID ES2a laut docs/CONTACT-SIGNATURE-REST-PLAN.md. Nur genannte Dateien. Katalog. Kein QES. Kein Refactor.`

---

## Token-Stufen

| Symbol | Wann |
|--------|------|
| 🟢 | Doc, Hinweistext, Checkliste |
| 🟡 | Migration / kleines PHP + 1 View |
| 🔴 | Manueller Smoke auf Dev |

---

## Recht (Kurz — Produkt-Hinweis, keine Beratung)

| Thema | Für dieses Feature |
|-------|---------------------|
| eIDAS **einfache** Signatur | Bild/Name unter Korrespondenz — ok wo **keine** Schriftform |
| **Schriftform / QES** | **nicht** abgedeckt — Hinweis in UI |
| DSGVO | Personenbezug; Zweck Korrespondenz; Zugriff beschränken; löschbar |
| GoBD | Wenn auf Beleg-PDFs: wer/wann nachvollziehbar; kein stilles Überschreiben ohne Spur |

---

## Ist (2026-11-08)

| Baustein | Stand |
|----------|--------|
| Kontakt: Unterschriftsbild | ❌ fehlt |
| Mail: Text-Signatur | ✅ `MailSignatureResolver` |
| Dokumentenverfasser: Text „Unterschrift/Name“ | ✅ |
| Belegdruck: Unterschrifts*zeilen* | ✅ AB u. a. |
| Medienbibliothek | ✅ `MediaStorage` / `dg_media` |

---

## Parent

| ID | Thema | Status | Token |
|----|--------|--------|-------|
| ES0 | Plan / Rechtshinweis | [x] 2026-11-08 | 🟢 |
| ES1 | Datenmodell speichern | [ ] | 🟡 |
| ES2 | Kontakt-UI Upload | [ ] | 🟡🔴 |
| ES3 | Brief / Dokumentenverfasser | [ ] | 🟡 |
| ES4 | Mail-Einbindung | [ ] | 🟡 |
| ES5 | Hinweise DSGVO/kein QES + Haken | [ ] | 🟢 |

---

## Mini-IDs

### ES0 — Plan

| ID | Aufgabe | Token | Status |
|----|---------|-------|--------|
| ES0a | Dieser Plan | 🟢 | [x] 2026-11-08 |

### ES1 — Speichern

| ID | Aufgabe | Token | Status |
|----|---------|-------|--------|
| ES1a | Entscheidung Doc: Speichern als `media_id` am Kontakt (empfohlen) vs. Contact-File — 5–10 Zeilen in diesem Plan §Entscheidung | 🟢 | [ ] |
| ES1b | Migration/Spalte oder Settings-Key laut ES1a (nur Schema, keine UI) | 🟡 | [ ] |
| ES1c | PHP-Accessor `ContactSignature::…` lesen/schreiben (klein) | 🟡 | [ ] |

**Prompt ES1a:** `Nur Mini-ID ES1a laut docs/CONTACT-SIGNATURE-REST-PLAN.md. Nur Docs-Entscheidung Speichermodell. Kein Code.`

### ES2 — Kontakt-UI

| ID | Aufgabe | Token | Status |
|----|---------|-------|--------|
| ES2a | Upload PNG/WebP (kein Zeichenpad in v1) in Kontakt-Bearbeiten · Katalog-Klassen | 🟡 | [ ] |
| ES2b | Vorschau + Löschen · Soft-Hinweis „kein QES / Schriftform“ | 🟡 | [ ] |
| ES2c | Smoke Dev: Upload sichtbar nach Reload | 🔴 | [ ] |

**Prompt ES2a:** `Nur Mini-ID ES2a laut docs/CONTACT-SIGNATURE-REST-PLAN.md. Upload-Feld Kontakt. Katalog. Kein Mail/Brief noch.`

### ES3 — Brief

| ID | Aufgabe | Token | Status |
|----|---------|-------|--------|
| ES3a | Dokumentenverfasser/Letterhead: optionales Bild aus Kontakt des Absenders | 🟡 | [ ] |
| ES3b | Druck/PDF-Stichprobe 1 Seite | 🔴 | [ ] |

### ES4 — Mail

| ID | Aufgabe | Token | Status |
|----|---------|-------|--------|
| ES4a | Post-Versand: optional `<img>` aus Kontakt-Signatur (CID oder URL laut bestehendem Mail-Stack) | 🟡 | [ ] |
| ES4b | Fallback: nur Text-Signatur wenn kein Bild | 🟡 | [ ] |
| ES4c | Smoke 1 Testmail Dev | 🔴 | [ ] |

### ES5 — Abschluss

| ID | Aufgabe | Token | Status |
|----|---------|-------|--------|
| ES5a | UI-Hinweis DSGVO + „ersetzt keine Schriftform“ final | 🟢 | [ ] |
| ES5b | Kurz in `TODOS.md` / Fach-ToDo verlinken · Plan-Parent [x] | 🟢 | [ ] |

---

## Entscheidung Speichermodell (ES1a ausfüllen)

| Option | Pro | Contra |
|--------|-----|--------|
| **A — `dg_media` + `signature_media_id` am Kontakt** | wie Logo/Appearance; Varianten/Rechte vorhanden | Medien-Tabelle |
| B — Datei unter Contact-Files | nah an Kontaktakten | schwerer in Mail/Brief wiederverwenden |

**Empfehlung v1:** **A**. (Haken setzen in ES1a-Chat.)

---

## Nicht-Ziele v1

- Qualifizierte Signatur / Vertrauensdiensteanbieter  
- Tablet-Zeichenpad (später optional)  
- Kunden-Gegenzeichnung / Workflow „Bitte hier signieren“  
- Automatisches Einbetten in alle historischen Belege  

---

## Empfohlene Reihenfolge (wenig Tokens)

1. **ES1a** (nur Doc, Free)  
2. **ES1b → ES1c** (ein Chat oder zwei)  
3. **ES2a → ES2b** · Smoke **ES2c**  
4. **ES3a** dann **ES4a/b** (nicht beides plus UI im selben Chat)  
5. **ES5a/b**

**Nächster Chat:**  
`Nur Mini-ID ES1a laut docs/CONTACT-SIGNATURE-REST-PLAN.md. Nur Docs-Entscheidung Speichermodell. Kein Code.`

---

## Verweise

| Datei | Rolle |
|-------|--------|
| [`UI-KLASSENKATALOG.md`](UI-KLASSENKATALOG.md) | Forms/Buttons |
| [`DOKUMENTENVERFASSER-TODO.md`](DOKUMENTENVERFASSER-TODO.md) | Briefpapier |
| `src/Mail/MailSignatureResolver.php` | Text-Signatur heute |
| `src/Media/*` | Medienbibliothek |
