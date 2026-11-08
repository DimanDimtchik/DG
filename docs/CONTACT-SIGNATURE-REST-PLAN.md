# User-/Kontakt-Unterschrift (Brief/Mail) — tokenarm

> **Eine Chat-Session = eine Mini-ID.**  
> Ziel: Unterschrifts**bilder** mit **Historie** speichern; in Brief/Mail nur die **aktive** Signatur des **angemeldeten Users**.  
> **Kein QES**, kein Schriftform-Ersatz (§ 126a BGB) — einfache elektronische Signatur / Layout + Nachvollziehbarkeit.

Stand: **2026-11-08** (Zielbild nach Fachvorgabe) · Katalog: [`UI-KLASSENKATALOG.md`](UI-KLASSENKATALOG.md)

---

## Fachziel (verbindlich)

| Regel | Bedeutung |
|-------|-----------|
| **Eigentümer** | Unterschrift gehört zum **User** (Mitarbeiter); Anzeige/Historie am **verknüpften Kontakt** |
| **Quellen** | (1) Bild-Upload (2) **Canvas**-Aufnahme Web (3) Aufnahme **Mitarbeiter-App** (4) **Import** aus externem Service |
| **Mail / Dokument** | Nur Unterschrift(en) des **angemeldeten** Users — keine fremde Signatur unter fremder Session |
| **Ändern** | Erlaubt: neue Version anlegen |
| **Verfolgbarkeit** | Alte Version bleibt; **Verwendungszeitraum** (`gültig von` / `gültig bis`) |
| **Darstellung** | Chronologische **Tabelle** am Kontakt |
| **Löschen** | **Nicht möglich** (kein UI-Löschen, kein DELETE der Historie) |

---

## Speichermodell (Entscheidung — festgelegt)

**Tabelle** `dg_user_signatures` (Migration `144_user_signatures.sql`):

| Feld | Zweck |
|------|--------|
| `id` | PK |
| `user_id` | Eigentümer (Login) |
| `contact_id` | Anzeige am Kontakt (Eigen-/Mitarbeiterkontakt) |
| `media_id` | Bild in `dg_media` (PNG/WebP) |
| `source` | `upload` \| `canvas` \| `app` \| `import` |
| `source_ref` | optional: Import-Service-ID / externe Referenz (kein Secret) |
| `valid_from` | Beginn Verwendungszeitraum |
| `valid_to` | Ende; **NULL** = aktuell aktiv |
| `created_at` / `created_by_user_id` | Anlage-Protokoll |

**Wechsel der Unterschrift:**

1. Aktive Zeile (`valid_to IS NULL`) → `valid_to = now()`  
2. Neue Zeile einfügen (`valid_from = now()`, `valid_to = NULL`, neues `media_id`)  
3. **Kein** UPDATE am Bild der alten Zeile, **kein** DELETE

**Aktiv für Versand:** genau eine Zeile pro `user_id` mit `valid_to IS NULL` (oder explizit „keine“ → nur Text-Signatur).

**Empfehlung Speichermedium:** Bildbytes in **`dg_media`** (wie Logo); Metadaten/Historie in der Signatur-Tabelle — nicht nur eine Spalte am Kontakt.

---

## Recht / Compliance (Kurz — keine Beratung)

| Thema | Für dieses Feature |
|-------|---------------------|
| eIDAS **einfache** Signatur | Bild unter Korrespondenz — ok wo **keine** Schriftform |
| **Schriftform / QES** | **nicht** abgedeckt — UI-Hinweis Pflicht |
| **Nachvollziehbarkeit** | Historie + Zeitraum stützt GoBD-/Beweisinteresse bei Korrespondenz |
| DSGVO | Personenbezug; Zweckbindung; Zugriff nur Berechtigte; **kein produktives Löschen** der Historie — echte Lösch-/Auskunftsbegehren = separates Verfahren (Aktenzeichen/Org), nicht Button „Löschen“ |
| Import-Service | AV/TOMs des Anbieters prüfen; CRM speichert nur übernommene Bildmetadaten |

---

## Ist (2026-11-08)

| Baustein | Stand |
|----------|--------|
| User-/Kontakt-Unterschriftshistorie | ✅ ES1–ES2 inkl. Smoke Dev; Canvas ES3a ✅ |
| Mail: Text-Signatur | ✅ `MailSignatureResolver` |
| Dokumentenverfasser: Text „Unterschrift/Name“ | ✅ |
| Belegdruck: Unterschrifts*zeilen* | ✅ |
| Medienbibliothek | ✅ |
| Canvas / App-Signatur-API | ✅ Canvas Web + Smoke (ES3a/b); App fehlt |
| Service-Import | ❌ fehlt |

---

## Warum tokenarm

| Regel | Bedeutung |
|-------|-----------|
| **1 ID = 1 kleiner Diff** | Schema **oder** eine Quelle **oder** eine Einbindung |
| **Kein QES-Anbieter bauen** | Import nur Adapter + gespeichertes Bild |
| **Kein Tenant-Hardcode** | Instanz-DB only |
| **Katalog-UI** | Tabelle/Form laut [`UI-KLASSENKATALOG.md`](UI-KLASSENKATALOG.md) |
| **Guard** | bei Views: `php bin/ui-form-spacing-selftest.php` |

**Chat-Prompt:**  
`Nur Mini-ID ES2a laut docs/CONTACT-SIGNATURE-REST-PLAN.md. Nur genannte Dateien. Katalog. Kein Löschen. Kein QES. Kein Refactor.`

---

## Token-Stufen

| Symbol | Wann |
|--------|------|
| 🟢 | Doc, Hinweistext |
| 🟡 | Migration / kleines PHP + 1 View |
| 🔴 | Manueller Smoke Dev / App |

---

## Parent

| ID | Thema | Status | Token |
|----|--------|--------|-------|
| ES0 | Plan / Zielbild | [x] 2026-11-08 | 🟢 |
| ES1 | Schema Historie + Accessor | [x] ES1d ✅ | 🟡 |
| ES2 | Kontakt-UI Tabelle + Upload | [x] ES2d ✅ | 🟡🔴 |
| ES3 | Canvas Web | [x] ES3b ✅ | 🟡🔴 |
| ES4 | Brief / Dokument (nur Session-User) | [ ] | 🟡 |
| ES5 | Mail (nur Session-User) | [ ] | 🟡 |
| ES6 | Mitarbeiter-App Aufnahme | [ ] | 🟡🔴 |
| ES7 | Import-Service Adapter | [ ] | 🟡 |
| ES8 | Hinweise + Plan-Haken | [ ] | 🟢 |

---

## Mini-IDs

### ES0 — Plan

| ID | Aufgabe | Token | Status |
|----|---------|-------|--------|
| ES0a | Erster Plan (Upload-only, veraltet) | 🟢 | [x] 2026-11-08 |
| ES0b | Zielbild: User-Eigentum, Quellen, Historie, kein Löschen | 🟢 | [x] 2026-11-08 |

### ES1 — Schema & API Kern

| ID | Aufgabe | Token | Status |
|----|---------|-------|--------|
| ES1a | ~~Speichermodell entscheiden~~ → **festgelegt** (Tabelle + media_id, siehe oben) | 🟢 | [x] 2026-11-08 |
| ES1b | Migration: Tabelle + Indizes (`user_id`, `contact_id`, `(user_id, valid_to)`) · **kein** ON DELETE CASCADE der Historie | 🟡 | [x] 2026-11-08 `144_user_signatures.sql` → `dg_user_signatures` · FK RESTRICT |
| ES1c | `UserSignatureStore` (Name final): `listForContact`, `activeForUser`, `appendNew` (schließt alte) · **keine** `delete`-Methode | 🟡 | [x] 2026-11-08 `src/User/UserSignatureStore.php` + Autoload |
| ES1d | Selftest: append → alte `valid_to` gesetzt · zweite aktiv · delete-API fehlt/wirft | 🟡 | [x] 2026-11-08 `bin/user-signature-selftest.php` |

### ES2 — Kontakt-UI (Historie + Upload)

| ID | Aufgabe | Token | Status |
|----|---------|-------|--------|
| ES2a | Tabelle am Kontakt: chronologisch **neueste zuerst**, Spalten Zeitraum, Quelle, Status, Vorschau | 🟡 | [x] 2026-11-08 Partial `contact-signature-history` · Detail + Bearbeiten |
| ES2b | Aktion „Neue Unterschrift (Upload)“ → `appendNew` · **kein** Löschen-Button | 🟡 | [x] 2026-11-08 Form unter Historie · `appendFromUpload` PNG/WebP |
| ES2c | Soft-Hinweis: kein QES / Schriftform · Historie unveränderlich | 🟢 | [x] 2026-11-08 `dg-flash--info` im Historie-Partial |
| ES2d | Smoke Dev: Upload → Tabelle 2 Zeilen nach zweitem Upload | 🔴 | [x] 2026-11-08 ganz-om.de Kontakt#1 · 2 Uploads → 1 aktiv + 1 beendet |

**Konvention Chronologie:** neueste zuerst (`ORDER BY valid_from DESC, id DESC` / Store `listForContact`).

### ES3 — Canvas Web

| ID | Aufgabe | Token | Status |
|----|---------|-------|--------|
| ES3a | Canvas-Pad (minimal) → PNG → `appendNew` · `source=canvas` | 🟡 | [x] 2026-11-08 Pad + `appendFromCanvasDataUrl` · JS/CSS Feature |
| ES3b | Smoke: Zeichnung erscheint als neue aktive Zeile, alte mit `valid_to` | 🔴 | [x] 2026-11-08 ganz-om.de · Canvas → source=canvas aktiv, Vorgänger closed |

**Prompt ES4a:**  
`Nur Mini-ID ES4a laut docs/CONTACT-SIGNATURE-REST-PLAN.md. Brief/Druck nur activeForUser(session). Kein Canvas.`

### ES4 — Brief / Dokument

| ID | Aufgabe | Token | Status |
|----|---------|-------|--------|
| ES4a | Dokumentenverfasser/Druck: Bild nur aus `activeForUser(session)` | 🟡 | [ ] |
| ES4b | Kein Kontakt-Picker fremder Signaturen | 🟡 | [ ] |
| ES4c | Stichprobe PDF 1 Seite | 🔴 | [ ] |

### ES5 — Mail

| ID | Aufgabe | Token | Status |
|----|---------|-------|--------|
| ES5a | Post: optionales `<img>` aus aktiver User-Signatur (CID/URL laut Mail-Stack) | 🟡 | [ ] |
| ES5b | Fallback Text-Signatur wenn keine aktive Bildzeile | 🟡 | [ ] |
| ES5c | Smoke 1 Testmail Dev | 🔴 | [ ] |

### ES6 — Mitarbeiter-App

| ID | Aufgabe | Token | Status |
|----|---------|-------|--------|
| ES6a | API: authentifiziertes Upload/Canvas-Ergebnis → `appendNew` · `source=app` | 🟡 | [ ] |
| ES6b | App-UI Skizze/Minimal: aufnehmen + speichern (eigenes Mini-Paket) | 🟡🔴 | [ ] |

### ES7 — Import-Service

| ID | Aufgabe | Token | Status |
|----|---------|-------|--------|
| ES7a | Doc: welche Service-API (Platzhalter-Interface `SignatureImportProvider`) | 🟢 | [ ] |
| ES7b | Adapter: externes Bild → media + `appendNew` · `source=import` + `source_ref` | 🟡 | [ ] |
| ES7c | Kein Auto-Löschen lokaler Historie bei Re-Import — immer neue Version | 🟡 | [ ] |

### ES8 — Abschluss

| ID | Aufgabe | Token | Status |
|----|---------|-------|--------|
| ES8a | UI-Texte final (DSGVO-Zweck, kein QES, kein Löschen) | 🟢 | [ ] |
| ES8b | `TODOS.md` / Parent-Haken · Plan ES1–ES7 Status | 🟢 | [ ] |

---

## Nicht-Ziele (Produkt)

- Qualifizierte Signatur (QES) / eigene Vertrauensdienste  
- Kunden-Gegenzeichnung („Bitte hier signieren“-Workflow)  
- Hard-DELETE / Papierkorb für Signaturzeilen  
- Fremde User-Signatur unter meiner Session in Mail/Brief  
- Stilles Überschreiben des gleichen `media_id` ohne neue Historienzeile  

---

## Empfohlene Reihenfolge (wenig Tokens)

1. **ES1b → ES1c → ES1d** (Kern Historie)  
2. **ES2a → ES2b** · Smoke **ES2d**  
3. **ES3a** (Canvas) **oder** **ES4a** (Brief) — nicht beides im selben Chat  
4. **ES5a/b** Mail  
5. **ES6** App · **ES7** Import (kann später)  
6. **ES8**

**Nächster Chat:**  
`Nur Mini-ID ES4a laut docs/CONTACT-SIGNATURE-REST-PLAN.md. Brief/Druck nur activeForUser(session). Kein Canvas.`

---

## Verweise

| Datei | Rolle |
|-------|--------|
| [`UI-KLASSENKATALOG.md`](UI-KLASSENKATALOG.md) | Tabelle/Form |
| [`DOKUMENTENVERFASSER-TODO.md`](DOKUMENTENVERFASSER-TODO.md) | Brief |
| `src/Mail/MailSignatureResolver.php` | Text-Signatur |
| `src/User/UserSignatureStore.php` | Historie-Accessor (kein delete) |
| `bin/user-signature-selftest.php` | ES1d Selftest (SQLite) |
| `views/partials/contact-signature-history.php` | Historie + Upload + Canvas + Hinweis |
| `UserSignatureStore::appendFromUpload` | PNG/WebP → media + appendNew |
| `UserSignatureStore::appendFromCanvasDataUrl` | Canvas-PNG → media + appendNew |
| `assets/js/contact-signature-canvas.js` | ES3a Zeichenfläche |
| `src/Media/*` | Bildspeicher |
| [`PHASE-B-REST-PLAN.md`](PHASE-B-REST-PLAN.md) | Server parallel, unabhängig |
