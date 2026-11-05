# Vertragsbaustein-Editor — tokenarmer Plan

Stand: 2026-11-05 · Menü: **HR** · Ziel: Paragraphen/Klauseln editierbar (RA/Admin), wiederverwendbar für Arbeitsvertrag + weitere Verträge.

**Nicht:** Sondercode pro Firma · Personen aus Kundenakten im Kern · Stripe/ELSTER.

---

## Zielbild (1 Satz)

Instanz-eigene **Vertragsvorlagen** mit nummerierten Bausteinen (HTML/Text + Platzhalter), Editor unter HR; PDF-Erzeugung liest Bausteine statt hartem PHP-Text.

---

## Abgrenzung

| Ja | Nein (jetzt) |
|----|----------------|
| Vorlage je Vertragstyp (Minijob/Teilzeit/Vollzeit/…) | Anwalt-KI / Auto-Rechtstext |
| Baustein = Abschnitt (Titel + Body, sortierbar) | Voll-WYSIWYG wie Word-Collaboration |
| Platzhalter `{{employee_name}}` … aus bestehendem Context | Tenant-Firmennamen im Repo |
| Version + „Freigegeben“-Flag (prüfbar) | Live-DEÜV / Lohn anfassen |
| Briefpapier wie bisher (Letterhead) | Dokumentenverfasser ersetzen |

**Reuse:** Letterhead/PDF-Pipeline; Platzhalter aus `EmploymentContractContext`.  
**Nicht vermischen:** Dokumentenverfasser = Einzelbriefe; dieser Editor = **Vorlagen-Bausteine**.

---

## Datenmodell (Instanz-DB)

```
dg_contract_templates
  id, code (z.B. employment_fulltime), title, status (draft|published),
  version, updated_by, updated_at, published_at

dg_contract_template_blocks
  id, template_id, sort_order, block_key (z.B. s1_start),
  title, body_html, is_annex (0|1), enabled (0|1)
```

Seed: einmalig aus heutigem `EmploymentContractClauses` → published v1 (kein leerer Start).

---

## UI (HR-Menü)

- Punkt: **Vertragsvorlagen** → `/app?page=hr-vertragsvorlagen`
- Liste Vorlagen · Detail: Bausteine auf/zuklappbar, Rich-Text (bestehende Writer-/Letterhead-HTML-Subset), Hoch/Runter, An/Aus
- Aktionen: Speichern (draft) · **Freigeben** (neue version, Audit) · Vorschau-PDF (Demo-Platzhalter, keine echte Akte)
- Recht: Admin oder HR mit Modulrecht (wie andere HR-Seiten)

---

## Erzeugung (Arbeitsvertrag)

1. Typ wählen → Template `employment_*` + published laden  
2. Fehlt Template → Fallback alter PHP-Klauseln (Übergang)  
3. Body = Bausteine in Reihenfolge, Platzhalter ersetzen  
4. PDF unverändert über Letterhead  

Später gleiche Pipeline: Abmahnung/Kündigung/Aufhebung (eigene `code`s).

---

## Phasen + Abbruch-Tore

| Gate | Inhalt | Abbruch wenn |
|------|--------|--------------|
| **V0** | Migration + Seed aus Clauses + Repository read | Seed ≠ inhaltlich gleich Fallback |
| **V1** | HR-Menü + Liste/Detail speichern (draft) | UI bricht Katalog/Guard |
| **V2** | Freigabe + Version + Audit | Kein Audit / Überschreiben ohne Version |
| **V3** | Generate nutzt published Template | PDF ohne Platzhalter oder leerer Vertrag |
| **V4** | Vorschau-PDF + 1 weiterer Typ (z.B. Abmahnung) anbinden | Scope-Creep vor V3 grün |

**Stop nach V3**, wenn nur Arbeitsvertrag nötig; V4 optional.

---

## Platzhalter (Startset)

`employer_*`, `employee_*`, `contract_*`, `job_type`, `work_location`, `working_hours`, `salary`, `vacation_days`, `min_wage`, `geringfuegig_month`, `today` — wie Context heute. Unbekannte `{{…}}` → leer + Warnung in Vorschau.

---

## Prüfbarkeit (RA / Betriebsprüfer)

- Freigabe mit User + Zeitstempel  
- Draft ≠ produktiv (Generate nur `published`)  
- Generiertes PDF weiter als Kontakt-Dokument (bestehend)  
- Hinweistext bleibt: Entwurf/Nachweis, keine Anwaltsberatung ersetzen  

---

## Nicht jetzt

Mehrsprachigkeit · Diff-UI · Word-Import · Klausel-Marktplatz · Branchen-Packs im Kern · parallele Editoren pro Modul.

---

## Nächster Schritt

**V0** Migration + Seed + Read-API; manuell: 1 Fulltime-PDF Fallback vs. Seed-Template byte-ähnlich (Inhalt), dann V1 UI.

### V0 Status (2026-11-05)

| Stück | Datei |
|-------|--------|
| Migration | `database/migrations/140_contract_templates.sql` |
| Katalog | `src/Hr/ContractTemplateCatalog.php` |
| Renderer | `src/Hr/ContractTemplateRenderer.php` |
| Repo/Seed | `src/Hr/ContractTemplateRepository.php` |
| Fallback | `EmploymentContractClauses::bodyHtml` → Katalog+Renderer |
| Selftest | `php bin/contract-template-v0-selftest.php` |

### V1 Status (2026-11-05)

| Stück | Ort |
|-------|-----|
| Menü | HR → Vertragsvorlagen |
| Liste | `views/modules/hr-vertragsvorlagen.php` |
| Editor | `views/modules/hr-vertragsvorlage-form.php` |
| Speichern | `ContractTemplateRepository::saveDraft` → Status `draft` |

**V2 als Nächstes:** Freigabe + Version + Audit. (PDF nutzt Freigabe erst ab V3.)
