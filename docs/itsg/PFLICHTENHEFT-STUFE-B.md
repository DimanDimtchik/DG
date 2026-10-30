# Pflichtenheft — DG CRM Lohn Stufe B (ITSG / direkter SV-Datenaustausch)

> **Version:** 1.0 · **Datum:** 2026-10-30 (Europe/Berlin) · **Paket:** `lb0a`  
> **Status:** Entwurf freigegeben zur Org-Nutzung (G4 ✅); **kein** produktiver Versand  
> **Rollen:** RA Steuerrecht · Steuerberater · Betriebsprüfer  
> **Outline-Quelle:** [`LOHNABRECHNUNG-IMPLEMENTIERUNG.md`](../LOHNABRECHNUNG-IMPLEMENTIERUNG.md) § G4 Kap. 1–9

---

## 0. Verbindliche Leitplanken

| Regel | Inhalt |
|-------|--------|
| Rechtsrahmen | § 95b SGB IV / ITSG-Systemuntersuchung für Software, die SV-Meldungen **direkt** an Annahmestellen übermittelt |
| Stufe A bleibt Fallback | Portal-CSV (`L9` / `PayrollSvPortalService`) bleibt betriebsfähig — Rollback-Pfad |
| Gates | **G4 ✅** (dieses Pflichtenheft) · **G3** weiterhin Pflicht vor `lb1+`/Versand-Pilot · **G2** nur für ELSTER/LSt, nicht ITSG-Schlüssel |
| Kein Code in LB0 | Kein PHP-Versand, kein MOD-ID, keine Annahmestellen-API |
| Folgepakete | LB1 Testaufgaben/XML → LB2 Pilot ≥2 → LB3 Prod-MOD-ID → LB4 Senden → LB5 ELStAM |

---

## 1. Produkt, Hersteller, Ansprechpartner

### 1.1 Produkt

| Feld | Festlegung |
|------|------------|
| Produktname | **DG CRM** — Modul Lohn (Stufe B: direkter GKV-/SV-Datenaustausch) |
| Hersteller / Betreiber | Ganz Soft / DG (Instanz-Betreiber je Domain; Code-Master `dg.ganz-om.de`) |
| Softwareklasse | Lohn-/Gehaltsabrechnungssoftware mit optionalem Meldekanal (nicht nur Portal-Hilfe) |
| Hersteller-ID / MOD-ID | **Platzhalter** — Vergabe erst nach ITSG-Verfahren (LB3); in Docs/Code nie echte Prod-IDs hardcoden |
| Vertriebsweg | Multi-Tenant-CRM: gleicher Code, **eigene DB** je Instanz (`AGENTS.md`) |

### 1.2 Ansprechpartner (Rollen, Namen organisatorisch nachpflegen)

| Rolle | Verantwortung | Nachweis |
|-------|----------------|----------|
| Fachlich Lohn/StB | Plausibilität Beitragsnachweis, DEÜV-Anlässe, Pilot-Abnahme | G4 O3 ✅ |
| Technisch Worker/Krypto | Signing-Worker, Schlüsselablage, Hetzner-Betrieb | G4 O3/O5 ✅ |
| ITSG-/Trust-Center-Kontakt | Infopaket, Testaufgaben-Koordination | G4 O2 ✅ |
| Datenschutz / AV | Auftragsverarbeitung Instanz ↔ Herstellerkanal | vor LB2 |

### 1.3 Dokumentationspflicht

Vor Systemuntersuchung müssen Pflichtenheft (dieses Dokument) und Systemdokumentation ([`SYSTEMDOKUMENTATION-OUTLINE.md`](SYSTEMDOKUMENTATION-OUTLINE.md)) aktuell und versioniert sein. Gesetzesänderungen → Rezertifizierungs-Trigger (Kap. 8).

---

## 2. Scope Stufe B (In-Scope)

Was DG **nach** ITSG-Freigabe und `lb1+`-Implementierung können soll:

### 2.1 Beitragsnachweis (elektronisch)

| Anforderung | Detail |
|-------------|--------|
| Inhalt | Monatliche Beitragssummen je Einzugsstelle/KK und Beitragsart (KV/RV/AV/PV, Umlagen, Pauschalen) |
| Quelle | Locked Payroll-Runs (`status=locked`, nicht superseded), Aggregation analog L9 |
| Ausgabe LB | XML/Nachrichtenformat laut ITSG-Vorgabe (konkret in **LB1**), signiert, an Annahmestelle |
| Idempotenz | Erneutes Senden nur als Korrektur/Storno-Pfad mit Audit; kein stilles Überschreiben |
| Parallel L9 | CSV-Kontrollliste bleibt zur Stichprobe/GoBD-Nebenakte erlaubt |

### 2.2 DEÜV-Meldungen

| Meldeart | Wann | Stufe-B-Pflicht |
|----------|------|-----------------|
| Anmeldung | Eintritt / Versicherungsbeginn | ja |
| Abmeldung | Austritt / Ende | ja |
| Änderung | Wechsel StKl.-fremde SV-Merkmale: Beitragsgruppe, Personengruppe, KK, Tätigkeitsschlüssel soweit geführt | ja |
| Unterbrechung / sonstige | nur wenn Stammdaten/Engine das Ereignis kennen | ja, wenn Anlass im CRM erfasst |
| Sofortmeldung | nur wenn Beschäftigungsart/Branche es verlangt | **bedingt** — Flag/Branche; Default aus für Normal-Büro |

Stammdaten-Basis: L2 (`employee_data`: SV-Nr., KK-BN, Beitrags-/Personengruppe, Eintritt/Austritt) + L9 Stammkontrolle als Qualitätsgate vor Versand.

### 2.3 ELStAM

Abruf Steuerklasse/Freibeträge — **LB5**, nicht Kern von LB0–LB4. In Stufe A weiter manuell (E5).

### 2.4 Mindestfunktionen vor Prod-Versand

1. Testmodus mit ITSG-Testaufgaben (LB1)  
2. Pilot ≥ 2 Anwender-Instanzen (LB2 / G4 O6)  
3. Prod-MOD-ID + Schlüsselbetrieb (LB3)  
4. Payload + Antwort + Ticket + Benutzer + Zeitstempel speichern (Kap. 6)

---

## 3. Out-of-Scope (verbindlich)

| Thema | Wohin / Warum |
|-------|----------------|
| Baulohn / SOKA / Winterbau | **LC** / Gate G5 — eigene Meldwege |
| UStVA / LStA / LStB über ELSTER | **L10** / Gate G2 — anderer Kanal (ERiC) |
| SEPA Lohnzahlung | **L8** — Zahlungsverkehr ≠ SV-Meldung |
| FiBu-Journal | **L7** — Buchung, nicht Meldung |
| Digitaler UV-/BG-Lohnnachweis | später (DRV/DGUV); nicht LB0–LB4-Kern |
| euBP | DRV-Pfad, eigenes Paket |
| Shared-Hosting-Prod für Signing | verboten — Hetzner/Root (Kap. 4) |
| Sonderlohn Gastronomie/TVöD/Seefahrt | Archiv Detailkonzept — nicht Stufe B |
| Automatisierte ITSG-Registrierung | organisatorisch, kein CRM-Feature |

---

## 4. Architektur

### 4.1 Zielbild

```
[CRM-Instanz PHP]  --Payload-Draft-->  [Signing-/Meldungs-Worker (Hetzner)]
        |                                        |
   Audit DB lokal                         ITSG-Schlüssel / MOD-ID
        |                                        |
   L9 CSV Fallback                    --> Annahmestelle / GKV-Netz
```

| Komponente | Verantwortung | Darf Prod-Schlüssel? |
|------------|---------------|----------------------|
| CRM Web (`dg.*` / Kundeninstanz) | UI, Aggregation aus locked Runs, Freigabe durch `lohn=full`, Audit-Meta | **Nein** |
| Worker (dediziert) | Signatur, Transport, Antwort-Parsing | **Ja** (nur hier) |
| All-Inkl Shared Host | Stufe-A-Betrieb, Portal-CSV | **Nein** — kein ITSG-Prod |

### 4.2 Anbindung an Bestehendes

| Baustein Stufe A | Rolle in B |
|------------------|------------|
| `PayrollSvPortalService` | Referenz-Aggregation; LB baut Versand **darauf** oder parallel mit denselben Summenregeln |
| `dg_payroll_sv_exports` | Muster für Export-Audit; LB braucht eigene Submission-Tabelle (LB1/LB4 Spec) |
| `StandardPayrollEngine` / Run-Results | Zahlenquelle — keine zweite Beitragslogik |
| `PayrollElsterService` | **Getrennt** halten (`form_type` LSt ≠ SV) |
| ERiC-Worker-Erfahrung (G2) | Betriebsmuster für Worker, nicht denselben Prozess |

### 4.3 Schlüsselablage (Anforderungen, ohne Implementierung)

- Getrennt von Web-Root und Git  
- Zugriff nur Worker-Service-Account  
- Rotation / Widerruf dokumentieren (Kap. 8)  
- Dev/Cloud-Agent: nur Testmaterial oder gar keins  

### 4.4 Multi-Instanz

Gleicher Code überall; Meldungen immer gegen **eigene** Instanz-DB. Kein Cross-Tenant-Versand. Herstellerkanal darf nur mandantenbezogene Payloads des beauftragten Arbeitgebers senden.

---

## 5. Datenquellen

### 5.1 Arbeitgeber

| Feld | Quelle Stufe A |
|------|----------------|
| Name, Steuernummer | Firma / `PayrollEmployerSettings` |
| Betriebsnummer (BN) | `employerSnapshot.betriebsnummer` — **Pflicht** vor Versand |
| U1/U2/U3-Teilnahme | `lohn`-Settings |
| Absender-Kennung MOD | LB3 (Prod) / Test-ID (LB1) |

Fehlende BN → Hard-Block Versand (wie L9-Warnung, in B Fehler statt nur CSV-Hinweis).

### 5.2 Mitarbeiter / DEÜV

| Feld | Quelle |
|------|--------|
| Name, Geburtsdatum | Kontakt |
| SV-Nummer | `social_security_number` |
| Eintritt / Austritt | `employee_data` |
| KK-Name / KK-BN | `health_insurance`, `health_insurance_company_number` |
| Beitragsgruppe / Personengruppe | `contribution_group_key`, `person_group_key` |
| Beschäftigungsart / Pfad | `payroll_employment_type`, Engine-`path` |

Stammkontrolle L9 (`ok`/`warn`/`fail`) ist **Vorfilter**: `fail` → kein DEÜV-Versand ohne bewusste Override-Protokollierung (Recht `lohn=full` + Begründung) — Override-Regel in LB4 festnageln.

### 5.3 Beitragsnachweis-Zahlen

Nur aus `dg_payroll_runs` locked + `dg_payroll_run_employees` status `ok` + `result_json` (KV/RV/AV/PV AN+AG, U1–U3, Flat/Pauschalen).  
Storno-Runs: keine Summen (wie L9). Correction: eigene Results; Portal-/Annahmestellen-Korrekturpfad dokumentieren.

### 5.4 Keine parallele Schattenrechnung

ITSG-Testaufgaben in LB1 müssen dieselbe Engine/Tabellenversion nutzen wie der Monatslauf (L4 Rate-Sets). Abweichung Engine ↔ Meldung = Prüfrisiko.

---

## 6. Protokollierung & Prüfbarkeit (GoBD)

Jede ausgehende oder versuchte Meldung speichert mindestens:

| Feld | Zweck |
|------|--------|
| `submission_id` | Primärschlüssel |
| Instanz / Mandant | Multi-Tenant-Trennung |
| `kind` | `beitragsnachweis` / `deuev_*` / … |
| Bezug `run_id` / `contact_id` / Periode | Belegkette zum Lohnlauf |
| Payload (roh oder verschlüsselt at-rest) | Nachvollziehbarkeit |
| Checksumme (SHA-256) | Integrität |
| Request-Zeit, User-ID | Wer hat ausgelöst |
| Transport-Status | `draft` / `signed` / `sent` / `accepted` / `rejected` / `error` |
| Antwort / Ticket / Fehlercode | Annahmestelle |
| Korrektur-Verweis | Vorgänger-Submission |

**Aufbewahrung:** analog Lohnunterlagen / steuerlich relevanter Unterlagen der Instanz; kein stilles Löschen nach Erfolg.

**Rechte:** Lesen Audit `lohn≥partial`; Senden nur `lohn=full` (Anschluss L0c).

---

## 7. Testkonzept

### 7.1 Ebenen

| Ebene | Inhalt | Paket |
|-------|--------|-------|
| Unit / Fixture | Aggregation = L9-Selftest-Niveau; XML-Schema-Fixtures | LB1 |
| ITSG-Testaufgaben | Offizielle mathematische/XML-Aufgaben | LB1 |
| Integration Worker | Signatur + Mock-Annahmestelle | LB1–LB2 |
| Pilot ≥2 Instanzen | Echte Test-MOD, kein Prod-Schlüssel in Dev | LB2 |
| Prod-Smoke | Erste Live-Meldung mit Vier-Augen + Rollback-Plan | LB3–LB4 |

### 7.2 Verbote

- Prod-ITSG-Schlüssel in Cloud-Agent, Git, Shared Host  
- Testaufgaben gegen Prod-Annahmestelle  
- „Grün“ nur weil CSV L9 stimmt — Versandpfad separat testen  

### 7.3 Abnahmekriterien vor LB4-Live

1. Alle vereinbarten ITSG-Testaufgaben bestanden (Nachweisdatei)  
2. Zwei Pilot-Instanzen ohne kritischen Defect  
3. Audit-Stichprobe: Payload ↔ Run-Summen stimmig  
4. Rollback auf L9 Portal innerhalb dokumentierter Frist geübt  

---

## 8. Betriebsführung

| Thema | Regel |
|-------|--------|
| Gesetzes-/Verfahrensänderung | Rezertifizierung / Software-Update-Pfad; Changelog Meldeformate |
| Incident Annahmestelle down | Queue + Retry-Policy; Fachseite sieht Status; Timeout → manuell L9 |
| Fehlermeldung rejected | Kein Auto-Retry ohne Sachbearbeiter; Begründungspflicht |
| Rollback | `payroll_sv_direct_live` (Name LB4) Default **aus**; Portal L9 bleibt |
| Schlüssel-Kompromiss | Sofort Widerruf + ITSG-Meldung nach Verfahren; Instanz auf Portal |
| Monitoring | Worker-Health, Queue-Länge, Reject-Rate — Alert an technisch Verantwortlichen |
| Jahreswechsel | Beitragstabellen L4 + Meldeformat-Version gemeinsam freigeben |

---

## 9. Parallelbetrieb Meldeportal (Pilot)

| Phase | CRM | Portal | Lexware |
|-------|-----|--------|---------|
| Stufe A / vor G3 | L9 CSV führend für SV-Hand | manuell | Schatten optional |
| G3-Pilot | L9 + Abrechnung DG | manuell | Schatten |
| LB2 Test-MOD | Direkt **Test**, parallel Portal | Stichprobe | — |
| LB4 Prod (Flag an) | Direkt führend | Fallback bei Incident | abgeschaltet nach Cutover-Befehl |
| Flag aus / Incident | kein Direktversand | wieder führend | — |

Während Pilot: jede Diskrepanz Portal ↔ DG-Aggregation in Abweichungsliste (Betriebsprüfer-Akte). Cutover nur mit Nutzerbefehl (wie SEPA/LStA-Flags).

---

## 10. Abnahme LB0a

| Prüfung | Ergebnis |
|---------|----------|
| Kap. 1–9 aus G4-Outline ausgefüllt | ✅ 2026-10-30 |
| Dateipfad `docs/itsg/` festgelegt | ✅ |
| Systemdok-Outline parallel | ✅ [`SYSTEMDOKUMENTATION-OUTLINE.md`](SYSTEMDOKUMENTATION-OUTLINE.md) |
| Kein Versand-Code / kein MOD-ID | ✅ |
| Abgrenzung L9 / L10 / LC / G3 | ✅ |
| `lb1+` weiter an G3 gebunden | ✅ |

**Nicht:** LB1-XML; Worker-Implementierung; Prod-Registrierung; Baulohn; Flag-Unlock.
