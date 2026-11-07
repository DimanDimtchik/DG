# Registerkasse — Spec Meldeweg §146a / SUBMIT DE (K19b)

> Verbindlich für DG CRM Registerkasse.  
> Rechtsgrundlage: **§ 146a Abs. 4 AO**, AEAO „1.16. Mitteilungspflicht“.  
> Speichern der Felder: **K19a** ✅. Übermittlung: **K19c** (erst nach Hetzner/Freigabe, analog ELSTER).  
> Primärer technischer Meldeweg (Ziel): **fiskaly SUBMIT DE** (REST). Alternativen (ELSTER-Portal manuell) bleiben außerhalb des Kerns dokumentiert.

---

## 1. Zweck

Die Mitteilungspflicht verpflichtet Steuerpflichtige, elektronische Aufzeichnungssysteme (ERS / „Kassen“) je Betriebsstätte dem Finanzamt zu melden — Anschaffung, Änderung, Außerbetriebnahme.

DG speichert die benötigten Stammdaten lokal (K19a) und soll später über einen freigeschalteten Meldeweg eine **validierte, vom Nutzer freigegebene** Meldung erzeugen und übermitteln (K19c).  
Dieses Dokument legt Ontologie, Feldmapping, Zustände und Guardrails fest — **ohne** Live-API-Aufruf.

**Nicht verwechseln**

| Thema | Hier (K19) | Anderswo |
|-------|------------|----------|
| Meldefelder speichern | K19a | — |
| Meldeweg / API-Spec | K19b (dieses Doc) | — |
| Senden / Transmission | K19c | — |
| TSE-Signatur je Bon | — | K3 / K7 |
| DSFinV-K / Prüferpaket | — | K17 / K18 |
| FiBu-ELSTER (UStVA) | — | Buchhaltung / ERiC |

---

## 2. Ontologie (SUBMIT DE ↔ DG)

| SUBMIT DE | Bedeutung | DG-Abbildung |
|-----------|-----------|--------------|
| **Taxpayer** | Steuerpflichtiger (eine Org ≈ ein Steuerpflichtiger) | `dg_cash_ers_taxpayer` + Firmendaten aus Instanz-Settings (Name/Adresse bei Übertragung ergänzen) |
| **Establishment** | Betriebsstätte | `dg_cash_locations` (Adresse: street/zip/city/country) |
| **Client (ERS)** | Elektronisches Aufzeichnungssystem = Kasse | `dg_cash_registers` + `dg_cash_ers_clients` |
| **TSS** | Technische Sicherheitseinrichtung | `dg_cash_tse_assignments` (SIGN DE: `tss_id` / Serien aus Provider; sonst „external“) |
| **Submission** | Meldung **je Establishment** (alle Clients dort) | später Job/Metadaten (K19c); nicht je einzelner Bon |

Eine Kasse gehört zu genau einer Filiale; eine Meldung listet alle Kassen der Filiale (aktiv und laut Recht auch ruhende, sofern noch dem Standort zuzurechnen — Außerbetriebnahme gesondert melden).

---

## 3. Feldmapping (K19a → API)

### 3.1 Taxpayer

| DG (`dg_cash_ers_taxpayer`) | SUBMIT DE | Pflicht vor Sendung |
|-----------------------------|-----------|---------------------|
| `tax_number` | `tax_number` (13 Ziffern, Bundesschema `^[0-9]{4}0[0-9]{8}$`) | ja |
| `tax_office_number` | `tax_office_number` (4 Ziffern; muss zu den ersten 4 der Steuernummer passen) | ja (oder ableitbar) |
| Firmendaten Settings | Name, Rechtsform, Anschrift, ggf. USt-IdNr. | ja (K19c aus CompanySettings) |
| — | Org-/JWT-Kontext fiskaly | K19c / Secrets |

Landes-Steuernummer → 13er-Bundesschema: BMF-Umwandlungstool (Hinweis bereits in UI K19a).

### 3.2 Establishment

| DG (`dg_cash_locations`) | SUBMIT DE | Pflicht vor Sendung |
|--------------------------|-----------|---------------------|
| stabiler Standort-Key | `establishment_id` (UUIDv4, **zufällig**, nicht aus Code ableiten) | ja (Mapping-Tabelle oder Spalte später K19c) |
| `name`, `street`, `zip`, `city`, `country` | Adress-/Bezeichnungsfelder Establishment | ja |
| — | `decommissioning_date` bei Standortaufgabe | bei Bedarf |

### 3.3 Client / ERS (zusätzlich zu SIGN-DE-Client)

| DG (`dg_cash_ers_clients`) | SUBMIT DE (Additional / External Client) | Pflicht vor Sendung |
|----------------------------|------------------------------------------|---------------------|
| `ers_type` | `type` (`1` Registrierkasse, `2` Taxameter, `3` Wegstreckenzähler) | ja |
| `manufacturer` | `manufacturer` | ja |
| `model` | `model` | ja |
| `serial_number` | `serial_number` (SeriennummerAS) | ja |
| `software` / `software_version` | `software` / `software_version` | ja / empfohlen |
| `date_acquisition` | `date_acquisition` | ja |
| `date_commissioning` | `date_commissioning` | ja |
| `date_tss_commissioning` | `date_tss_commissioning` | empfohlen |
| `date_decommissioning` / `decommissioning_reason` | Außerbetriebnahme | bei Stilllegung |
| `remarks` | `remarks` | optional |
| `dg_cash_registers.code` | Client-Serial / Zuordnung SIGN DE `client_id` | über TSE-Assignment |

**SIGN DE-Pfad (Zielprodukt):** Client/TSS-Stammdaten weitgehend aus SIGN DE; DG liefert **Additional Data** (K19a-Felder).  
**External-Pfad:** vollständige Client- + TSS-Daten manuell (Mock / Nicht-fiskaly) — K19c entscheidet Adapter.

Kernvollständigkeit lokal: `CashErsClientRepository::isCoreComplete` (Hersteller, Modell, Seriennr., Software, Anschaffung, Inbetriebnahme).

### 3.4 TSS

| Quelle | SUBMIT DE |
|--------|-----------|
| Assignment `tss_id`, Serien aus Provider-Status | SIGN DE: automatisch / gespiegelt |
| External | `UpsertExternalTss` (u. a. hex-Seriennummer laut API-Schema, `type`, `bsi_id`, `date_commissioning`) |

Ohne TSS-Zuordnung: aktive Kasse darf nicht als „in Betrieb gemeldet“ gelten — Speichern der Meldefelder bleibt erlaubt (K19a); Sendung blockt (K19c).

---

## 4. Melde-Workflow (Zielzustände)

Pro Filiale (Establishment), chronologisch:

```text
1. Stammdaten lokal pflegen (K19a)
2. Provider-Ressourcen anlegen/aktualisieren (Taxpayer, Establishment, Client+Additional, TSS)
3. Submission erzeugen (Create) inkl. data_transmitter
4. Validierung (intern + ERiC-Plausibilität)
5. Preview-PDF laden → Status READY_FOR_TRANSMISSION
6. Explizite Nutzer-Zustimmung (legal_consent) im CRM
7. Transmission auslösen
8. Nachweis (XML/PDF) speichern + Audit
```

| Phase | CRM-Status (Soll K19c) | API (orientierend) |
|-------|------------------------|--------------------|
| Nur lokal | `draft` | — |
| Sync/Validierung | `validating` / `validation_failed` | Create + Validate |
| Vorschau geholt | `preview_ready` | VALIDATION_SUCCEEDED → Download Preview → READY_FOR_TRANSMISSION |
| Freigabe UI | `consent_pending` → `consent_given` | `legal_consent` |
| Gesendet | `transmitted` / `failed` | Transmission + Ergebnis |

**Pflicht für Integratoren:** Steuerpflichtiger (oder Bevollmächtigter) muss Preview gesehen und Korrektheit bestätigt haben — **kein** stilles Auto-Senden.

Reihenfolge: bei Fehlschlag zuerst denselben Zustand erneut senden, nicht „überspringen“ (Timeline der Mitteilungspflicht).

---

## 5. Guardrails (wie ELSTER)

| Regel | Bedeutung |
|-------|-----------|
| Kein Live auf Kasserver | K19c erst nach Hetzner/Freigabe — gleiches Muster wie ERiC |
| TEST ≠ LIVE | getrennte Keys/Org; UI Umgebung klar kennzeichnen |
| Secrets nicht im ZIP/UI | API-Key/Secret nur Settings/Env |
| Kein Fake-Nachweis | ohne echte Transmission kein „gesendet“-Badge |
| Audit | jeder Sync/Preview/Consent/Send in `dg_cash_audit_log` (`ers.*`) |
| Kein Tenant-Hardcode | Firmendaten nur aus Instanz-DB/Settings |

Add-on-Preis SUBMIT (K0i) ist Shop/Vertrag — nicht in `src/`.

---

## 6. geplante Persistenz (K19c, nicht jetzt)

Ergänzend zu K19a (nur Vorschlag für spätere Migration):

| Entität | Zweck |
|---------|--------|
| UUID-Mapping Location → `establishment_id` | stabile zufällige UUID, einmal vergeben |
| `dg_cash_ers_submissions` | je Filiale: Status, provider_submission_id, preview_checksum, transmitted_at, error |
| Nachweis-Dateien | unter `storage/` + `file_checksum` |

K19a bleibt unverändert Speichern ohne diese Tabellen.

---

## 7. Abgrenzung Umsetzung

| Mini-ID | Was |
|---------|-----|
| **K19a** | ✅ lokale Felder + UI |
| **K19b** | dieses Spec-Doc |
| **K19c** | 🔴 Client/Adapter, Preview, Consent-UI, Transmission, Nachweise — erst nach Freigabe |

**Nicht tun**

- ELSTER-/SUBMIT-Calls in K19a/K19b
- Steuernummer erfinden oder Landesnummer als 13er vortäuschen
- UUID aus Kassen-`code` ableiten
- Meldung ohne Preview-Consent
- FiBu-UStVA-ELSTER und Kassenmeldung in einem Button vermischen

---

## 8. Referenz

- § 146a Abs. 4 AO · AEAO 1.16  
- fiskaly SUBMIT DE API: [workspace.fiskaly.com/api/sign-de/submission/v1](https://workspace.fiskaly.com/api/sign-de/submission/v1/)  
- Produkt/Preisrahmen: [`REGISTERKASSE-TSE-K0-FISKALY.md`](REGISTERKASSE-TSE-K0-FISKALY.md) (K0i Add-on)  
- Plan: [`REGISTERKASSE-TSE-PLAN.md`](REGISTERKASSE-TSE-PLAN.md) K19  
- Lokale Tabellen: Migration `154_cash_ers_melde.sql`
