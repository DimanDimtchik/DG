# Firmen-Nachweise — Matrix (Bescheid / Schreiben / Auszug)

> Stand: 2026-11-01  
> Zweck: Welche Firmeneinstellung welchen Nachweis braucht — **ohne** alles zum Pflicht-Upload zu machen.  
> Stufen: **A** FA-Gestattung/Bescheid · **B** Behörden-/Träger-Schreiben · **C** Register/Auszug/Status  
> Bezug: [`UST-IST-SOLL-VERSTEUERUNG.md`](UST-IST-SOLL-VERSTEUERUNG.md) · Firmendaten `CompanyExtendedSettings` · Lohn/Baulohn später

**Regel:** Upload ist **Pflicht**, wenn ohne Nachweis ein Feature steuerlich/meldepflichtig falsch laufen würde. Sonst empfohlen oder optional.

---

## Legende Upload

| Symbol | Bedeutung |
|--------|-----------|
| **Pflicht** | Feature/Status darf ohne Datei nicht aktiv werden (wie Ist-Versteuerung) |
| **Empfohlen** | Für Prüfung, Support und Meldewege stark sinnvoll |
| **Optional** | Hilfreich, kein Blocker |
| **—** | Kein Dokument üblich / nicht sinnvoll als Pflicht |

---

## 1. Steuer / USt / FA

| Einstellung / Kennung | Stufe | Upload | Hinweis | CRM heute |
|----------------------|-------|--------|---------|-----------|
| Soll-/Ist-Versteuerung (§ 20) | A | **Pflicht** bei Ist | FA-Gestattung; Historie + Stichtag | ✅ Bescheid-Upload |
| Dauerfristverlängerung (+ Sondervorauszahlung) | A | **Pflicht**, wenn als aktiv geführt | FA-Bescheid/Bestätigung | ⬜ Feld/Feature fehlt |
| Organschaft | A / Vertrag | **Pflicht**, wenn aktiv | FA + Organschaftsvertrag | ⬜ |
| Steuernummer (ESt/USt-FA) | B | Empfohlen | FA-Schreiben / ELSTER-Zuordnung | ✅ Feld |
| USt-IdNr. | B | Empfohlen | BZSt-Vergabe | ✅ `tax_numbers.ust` / `vat_id` |
| Steuer-ID | B | Empfohlen | **natürliche Person** (Inhaber/GF), nicht Firma | ✅ Feld |
| Wirtschafts-Identifikationsnummer | B | Empfohlen | sobald zugeteilt | ✅ Feld vorbereitet |
| Gewerbesteuer-Hebesatz / GewSt-FA | C | Optional | oft nur Zahl + FA-Zuordnung | ✅ Hebesatz Orientierungsrechner |
| Kleinunternehmer § 19 | C | Optional | oft kein Bescheid; Korrespondenz möglich | ✅ Flag + Zeitraum |
| Photovoltaik § 12 Abs. 3 | C | Optional | gesetzliche Regelung; Objekt-/Rechnungsnachweis eher am Beleg | ✅ Flag |
| Reverse Charge § 13b (Firma darf) | C | Optional | fachlicher Status, selten eigener Bescheid | ✅ Flag |
| Steuerfreie Umsätze § 4 | C | Optional | | ✅ Flag |
| Finanzamt (Zuständigkeit) | B | Optional | aus Steuernummer ableitbar; Schreiben hilfreich | ✅ Liste |

---

## 2. Register / Rechtsträger

| Einstellung / Kennung | Stufe | Upload | Hinweis | CRM heute |
|----------------------|-------|--------|---------|-----------|
| Handelsregister (Gericht + Nr.) | C | Empfohlen | aktueller HR-Auszug | ✅ |
| Rechtsform / Firma | C | Empfohlen | Gesellschaftsvertrag (GmbH) bei Bedarf | ✅ Typ/Name |
| Gesellschafter / Anteile | C | Empfohlen | Liste/Vertrag — Prüfer/GoBD | ✅ owners |
| Gewerbeanmeldung | C | Empfohlen | Bescheinigung Gewerbeamt | ⬜ kein dediziertes Feld |
| Impressum-Angaben | C | — | hängen an HR/USt-Id; Auszug hilft | Website-Rechtstexte |

---

## 3. Kammern / Verbände / UV

| Einstellung / Kennung | Stufe | Upload | Hinweis | CRM heute |
|----------------------|-------|--------|---------|-----------|
| IHK Mitgliedsnummer | B–C | Empfohlen | Beitragskonto/Bescheinigung | ✅ institutions.ihk |
| HWK Mitgliedsnummer | B–C | Empfohlen | Handwerksrolle ggf. extra | ✅ institutions.hwk |
| Handwerksrolle / Meisterpflicht | C | Empfohlen | Eintragungsbescheinigung HWK | ⬜ oft unter HWK abbilden |
| Berufsständische Kammer | B–C | Empfohlen | RAK, StBK, Ärztekammer, … | ✅ professional_chambers |
| Versorgungswerk | B | Empfohlen | Mitgliedsnummer | ⬜ unter Mitgliedschaften möglich |
| Berufsgenossenschaft / UK | B | Empfohlen | Unternehmens-/Mitgliedsnummer | ✅ bg_data |
| UV-Lohnnachweis Zugang/PIN | B | Empfohlen | sobald digitaler Lohnnachweis | ⬜ Lohn-Phase |
| Gewerkschaft / Betriebsrat | C | Optional | Kontakt, selten Nachweis | ✅ institutions |
| Sonstige Mitgliedschaften | C | Optional | Innungen, Verbände | ✅ memberships |

---

## 4. Lohn / Agentur / SV (sobald Lohn produktiv)

| Einstellung / Kennung | Stufe | Upload | Hinweis | CRM heute |
|----------------------|-------|--------|---------|-----------|
| Betriebsnummer Agentur für Arbeit | B | Empfohlen | Zuteilungsbescheid (8-stellig) | ✅ employment_agency |
| Minijob-Zentrale Betriebsnr./Absender | B | Empfohlen | eigenes Schreiben | ⬜ Lohn-Settings / L1 |
| Umlagen U1/U2 (Insolvenzgeld U3) | B | Optional | oft über KK; AG-Stammdaten | ✅ lohn-Defaults |
| Krankenkasse Absender/BBNR | B | Empfohlen | Meldeweg | Lohnmodul |
| DEÜV / SV-Betriebsdaten | B | Empfohlen | | Lohnmodul |
| ELStAM | — | — | Hersteller-/Softwarezulassung, kein Mandanten-Bescheid-Upload | später ERiC |

---

## 5. Bau / SOKA / Branchen (Flag „Baulohn“ o. Ä.)

Nur anzeigen/pflichtig machen, wenn Branche oder Mandanten-Flag aktiv.

| Einstellung / Kennung | Stufe | Upload | Hinweis | CRM heute |
|----------------------|-------|--------|---------|-----------|
| SOKA-BAU Betriebs-/Mitgliedsnummer | B | Empfohlen → **Pflicht** bei Baulohn-Meldung | Urlaub/ZVK/Berufsbildung | Flag vorbereitet, Logik Stufe C |
| Malerkasse / Dachdecker o. Ä. | B | wie SOKA | anderer Träger | ⬜ |
| Arbeitnehmerentsendung A1 | B | Empfohlen projektbezogen | nicht Stammdaten-Dauer | ⬜ |
| Präqualifikation / VOB-Nachweise | C | Optional | Auftragswesen | ⬜ |

---

## 6. Zahlungsverkehr

| Einstellung / Kennung | Stufe | Upload | Hinweis | CRM heute |
|----------------------|-------|--------|---------|-----------|
| Bankverbindung IBAN/BIC | C | Optional | Kontoauszug/Bestätigung | ✅ bank_accounts |
| SEPA-Gläubiger-ID | B | Empfohlen bei Lastschrift | Vergabeschreiben | ⬜ prüfen ob Feld existiert |

---

## Umsetzungsleitlinie (wenn Code folgt)

1. **Gemeinsames Nachweis-Objekt** je Einstellung: Datei + Aussteller + Datum + Aktenzeichen + `valid_from`/`valid_to` + AuditLog (Vorbild Ist-Versteuerung).
2. **Pflicht nur Stufe A** und ausgewählte Melde-Kennungen bei aktivem Feature (Baulohn → SOKA).
3. **Kein Upload-Zwang** für § 19 / PV nur wegen Flag — Hinweistext reicht; optional Anhang.
4. Branchennachweise hinter **Industrie-/Baulohn-Flag**, nicht auf jeder Instanz.
5. Steuer-ID immer an **Person** (Owner/GF) hängen, nicht nur an „Firma“.

---

## Bewusst nicht in dieser Matrix

- Einzelbelege (Rechnungen) — Belegablage  
- Mitarbeiter-Steuerkarten / SV-Ausweise — Personalakte Lohn  
- Mein-ELSTER-Zertifikat der Software — Hersteller/`elster.local.php`  
- Alle Portal-Formulare ELSTER — nach ERiC, verfahrenweise  

---

## Umsetzung

- [x] **2026-11-01:** `CompanyProofStorage` + UI Firmendaten „Nachweise“ (empfohlen/optional) — Migration `135_company_proofs.sql`  
- [x] Ist-Versteuerung bleibt eigene Pflicht-Ablage (`VatTaxationSettings`)  
- [ ] SEPA-Gläubiger-ID + Gewerbe (nächster kleiner Block)  
- [ ] Dauerfrist mit Pflicht-Bescheid  
- [ ] Baulohn: SOKA-Nachweis an Flag ([`LOHNABRECHNUNG-ERWEITERUNG.md`](LOHNABRECHNUNG-ERWEITERUNG.md) Stufe C)  
- [ ] Ist optional auf `CompanyProofStorage` umbiegen (kein Muss)
