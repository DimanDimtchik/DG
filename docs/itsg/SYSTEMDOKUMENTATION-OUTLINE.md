# Systemdokumentation Outline — DG CRM Lohn Stufe B (ITSG)

> **Version:** 1.0-outline · **Datum:** 2026-10-30 · **Paket:** `lb0a`  
> Volltext-Systemdok wird bei LB1/LB3 ergänzt (Formate, Schlüsselverfahren, Testnachweise).  
> Pflichtenheft: [`PFLICHTENHEFT-STUFE-B.md`](PFLICHTENHEFT-STUFE-B.md)

---

## A. Systemübersicht

| Punkt | Kurzbeschreibung |
|-------|------------------|
| A1 | Zweck: elektronischer SV-Datenaustausch aus DG CRM nach ITSG-Vorgaben |
| A2 | Abgrenzung: Stufe A = Portal-CSV; Stufe B = signierter Direktkanal |
| A3 | Nutzer: Lohnsachbearbeitung Instanz (`lohn` partial/full) |
| A4 | Nicht-Nutzer: öffentliche Website, Shop, andere CRM-Module ohne Recht |

## B. Technische Umgebung

| Punkt | Kurzbeschreibung |
|-------|------------------|
| B1 | CRM: PHP 8.2/8.3, MariaDB je Instanz, Deploy Master → Sync |
| B2 | Worker: Linux Root (Hetzner), getrennt vom Shared-Hosting-Web |
| B3 | Netz: ausgehend nur freigegebene Annahmestellen-Endpunkte (LB1 konkretisieren) |
| B4 | Zeit: UTC in Logs; Anzeige Europe/Berlin in UI |

## C. Komponenten & Schnittstellen

| ID | Komponente | Schnittstelle |
|----|------------|---------------|
| C1 | Monatslauf / Engine | interne PHP-API, DB `dg_payroll_*` |
| C2 | Aggregator (L9-Basis) | gleiche Summenregeln für Portal und Direkt |
| C3 | Submission-Service (LB) | erzeugt Payload, schreibt Audit, übergibt Worker |
| C4 | Signing-Worker | Signatur + Transport + Antwort |
| C5 | Annahmestelle | ITSG/GKV-Netz (extern) |
| C6 | ELSTER/ERiC | **keine** gemeinsame Pipeline mit SV |

## D. Datenhaltung

| Punkt | Kurzbeschreibung |
|-------|------------------|
| D1 | Lohnlauf-Ergebnisse unveränderlich nach `lock` (Korrektur nur neuer Run) |
| D2 | Submission-Archiv mit Payload-Checksumme |
| D3 | Keine Prod-Schlüssel in DB der Web-Instanz |
| D4 | Backup: Instanz-Backup umfasst Audit; Schlüsselbackup nur Worker-Verfahren |

## E. Sicherheit

| Punkt | Kurzbeschreibung |
|-------|------------------|
| E1 | Trennung Web vs. Worker |
| E2 | Least Privilege Service-Accounts |
| E3 | TLS zum Worker; Authentifizierung Instanz→Worker (LB1 spezifizieren) |
| E4 | Keine Secrets in Git / Cloud-Agent-Logs |
| E5 | Rechte-Matrix L0c: Senden nur `full` |

## F. Betriebsprozesse

| Prozess | Skizze |
|---------|--------|
| F1 | Freigabe Monat → optional Beitragsnachweis-Direkt |
| F2 | DEÜV-Ereignis aus Stammdatenänderung / Ein-Austritt |
| F3 | Reject-Behandlung / Korrektur |
| F4 | Incident → Flag aus → Portal L9 |
| F5 | Rezertifizierung bei Format-/Gesetzesänderung |

## G. Test & Nachweis

| Punkt | Ablage (später) |
|-------|-----------------|
| G1 | ITSG-Testaufgaben Protokolle | `docs/itsg/tests/` (LB1) |
| G2 | Pilotberichte 2 Instanzen | `docs/itsg/pilot/` (LB2) |
| G3 | Prod-Smoke Checkliste | mit LB3/LB4 |

## H. Offene Punkte für LB1 (nicht LB0)

1. Konkrete XML/XSD- bzw. Nachrichtenversionen  
2. Exact Endpoint-URLs Test vs. Prod  
3. Tabellenmodell `dg_payroll_sv_submissions` (Name frei)  
4. Worker-Protokoll (Queue-Format)  
5. Sofortmeldungs-Branchenmatrix  

## I. Abnahme Outline LB0a

| Prüfung | Ergebnis |
|---------|----------|
| Kapitel A–H skizziert | ✅ 2026-10-30 |
| Verweis Pflichtenheft | ✅ |
| Keine Implementierungsdetails Versand | ✅ |
