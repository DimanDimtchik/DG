# DG CRM — ERP-Erweiterung nach Lastenheft V2

Stand: **2026-10-27** · Quelle: Adobe-PDF `lastenheft_erp_erweiterung_v2.pdf`
Ziel: PDF-Inhalte in die bestehende DG-CRM-Architektur einordnen, ohne Sondercode pro Kundeninstanz.

---

## 1. Einordnung

Das Lastenheft nennt vier Blöcke:

1. Lohnabrechnung Standard + später Baulohn
2. Zertifizierungen / Meldewege
3. Maschinen-Kostenrechnung mit FiBu-Autopilot
4. Maschinenwissen: Handbuch-PDF + Akademie-Video je Fehlercode

Für DG CRM gilt:

- **Code zentral**, Instanzen nur eigene DB, `config/*.local.php` und `storage/`.
- **Lohn** bleibt gestaffelt: erst Normal-Lohn wie Lexware Office, danach ITSG/SV-Automatik, danach Baulohn.
- **Maschinenkosten** bauen auf dem vorhandenen Rezeptur-Modul auf, nicht auf einem neuen parallelen ERP-Modul.
- **Akademie** ist bereits das Zielmodul für Videos; Maschinenwissen soll daran verlinken.

### Bereinigung: Bestand vs. neue Aufgaben

| Thema aus PDF | DG CRM Stand | Bewertung |
|---------------|--------------|-----------|
| Zeiterfassung als Lohn-Vorbereitung | vorhanden: Stempelzeiten, Schichten, Urlaub/Krankheit, Rückstellungen, Lohn-Export | **nicht neu bauen** |
| Fremde Lohnabrechnungen ablegen | vorhanden: Mitarbeiter-Dokumenttyp `payroll_slip` | **nicht neu bauen** |
| Maschinen / Arbeitsplätze | vorhanden: `dg_work_centers`, Maschinen-Wizard, Stundensatz | **nicht neu bauen** |
| Rezeptur / Routing / Snapshots | vorhanden: BOM, Routing, Kalkulations-Snapshots, Soll/Ist, Graph | **nicht neu bauen** |
| Akademie-Videos | vorhanden: Kurse/Module, Video-Dateien, VTT-Regeln, Fortschritt | **nicht neu bauen** |
| Standard-Lohnabrechnung berechnen | nicht vorhanden | **neues D0-A-Paket** |
| Direkte SV-/ELStAM-/euBP-Meldungen | nicht vorhanden | **neues D0-B/C1-Paket nach Hetzner** |
| Baulohn / SOKA / Saison-KUG | nicht vorhanden | **späteres D0-C-Paket** |
| FiBu-Gemeinkosten automatisch auf Maschinenstunde umlegen | nicht vorhanden | **neue Rezeptur-/FiBu-Erweiterung** |
| Fehlercode -> Handbuch + Akademie-Video | nicht vorhanden als Maschinen-Wissensbrücke | **kleine neue Erweiterung** |

---

## 2. Lohnabrechnung: CRM-Anpassung

Umsetzungsplan (token-sparend): [`LOHNABRECHNUNG-IMPLEMENTIERUNG.md`](LOHNABRECHNUNG-IMPLEMENTIERUNG.md).

### Reihenfolge

| Stufe | Inhalt | DG-Entscheidung |
|-------|--------|-----------------|
| D0-A | Standard Lohn/Gehalt, Minijob, Lohnabrechnung PDF, Lohnkonto, FiBu-Buchung | zuerst bauen |
| D0-B | Beitragsnachweise, DEÜV, ELStAM, direkte SV-/Steuer-Meldungen | nach D0-A und nach Hetzner/ERiC |
| D0-C | Baulohn, SOKA-BAU, Saison-KUG, Bau-Sofortmeldungen | eigenes Modul nach Standardlohn |

### Fachliche Leitplanken

- Bis D0-A produktiv ist: DG CRM bleibt bei **Zeiterfassung + Lohn-Export + Ablage fremder Lohnabrechnungen**.
- Direkte SV-Meldungen aus DG erfordern ITSG-Systemuntersuchung; vorher bleibt das SV-Meldeportal als Übergang.
- Lohnsteuer-Anmeldung und ELStAM gehören technisch zum ERiC-/Zertifikatsblock nach dem Server-Umzug.
- Baulohn darf Standardlohn nicht blockieren; es wird später per Mandanten-/Firmenflag aktiviert.

---

## 3. Zertifizierungen / externe Freischaltungen

| Bereich | Aus Lastenheft | DG-CRM-Einordnung |
|---------|----------------|-------------------|
| ITSG | GKV-Systemprüfung / Softwarezertifikat | erst für direkten SV-Versand aus DG, nicht für Export/Portalbetrieb |
| Bundesagentur für Arbeit | Betriebsnummer / Absenderkennung | Arbeitgeber-Stammdaten + Meldeweg-Konfiguration |
| ELSTER | Organisationszertifikat `.pfx` | nach Hetzner; sichere Ablage in `config/elster.local.php`, Offline-Backup Pflicht |
| BZSt / DRV / DGUV | ELStAM, euBP, UV-Lohnnachweis | nach D0-A/D0-B, mit AuditLog und Testmodus |

Wichtig: Es gibt keine allgemeine Finanzamt-Softwarefreigabe für das CRM. Relevant sind konkrete Meldewege, Zertifikate, Schnittstellentests und GoBD-/Prüfpfad-Nachweise.

---

## 4. Maschinen-Kostenrechnung & FiBu-Autopilot

### Bestehender DG-Anker

Das Rezeptur-Modul hat bereits:

- `dg_work_centers` für Maschine / Arbeitsplatz
- Arbeitsplan / Routing je Rezept
- Maschinenstundensatz aus Anschaffung, Lebensdauer, kW, Fläche und Bedienern
- Snapshots, damit historische Kalkulationen nicht rückwirkend verändert werden

### Erweiterung aus dem Lastenheft

Der neue Baustein ist **Gemeinkosten-Aufschlag aus der FiBu**:

1. Admin definiert Kontengruppen für Gemeinkosten, z. B. Buchhaltung, IT, Hausmeister, Raumkosten.
2. System summiert die gebuchten Kosten je Zeitraum aus dem Hauptbuch.
3. System teilt sie durch die Maschinenlaufzeit aller aktiven Maschinen.
4. Ergebnis: dynamischer EUR-Aufschlag pro Maschinenstunde.
5. Rezeptur-Kalkulation speichert den verwendeten Aufschlag als Snapshot.

### Start jetzt möglich

Dieser Block muss nicht bis nach Lohn/ITSG warten, wenn er strikt als **nicht buchungswirksamer Kalkulationsvorschlag** umgesetzt wird:

- Er liest Hauptbuch-/Kontengruppen und Maschinenstammdaten nur aus.
- Er schreibt nur Einstellungen und Kalkulations-Snapshots.
- Er erzeugt keine automatischen FiBu-Buchungen.
- Er kann deshalb parallel zu A/B/C laufen und später erweitert werden.

### Grenzen

- Keine stillen rückwirkenden Änderungen gebuchter Produktionsläufe.
- Kein automatisches Buchen ohne Freigabe.
- FiBu-Autopilot zuerst als **Vorschlag / Kalkulationshilfe**, später optional buchungswirksam.

---

## 5. Maschinen-Belegungsplan, Durchsatz, Zusatzstoffe, Wartung

Dieser Nutzerplan war **nicht** im Adobe-PDF enthalten und ist im aktuellen Repo noch nicht als Plan angekommen. Er wird hier als eigener frueher Maschinenblock ergaenzt.

### Zielbild

Je Maschine soll DG CRM nicht nur einen Stundensatz kennen, sondern auch:

- welche Rohstoffmenge die Maschine pro Stunde verarbeiten kann
- welche Produktmenge pro Stunde herauskommt
- welche Zusatzstoffe oder Verbrauchsmaterialien notwendig sind, z. B. Verpackungsmaterial
- wann die Maschine belegt, frei oder in Wartung ist
- welcher Wartungsturnus gilt und welche Hilfsmittel/Kosten anfallen

### Teilpakete

| Paket | Inhalt | Wirkung |
|-------|--------|---------|
| M0a | Datenmodellplan | [`MASCHINENBLOCK-M0A-DATENMODELL.md`](MASCHINENBLOCK-M0A-DATENMODELL.md) |
| M0b | Migrationen + Repository | Tabellen fuer Kapazitaet, Zusatzmaterial und Wartung |
| M1 | Zusatz-/Verpackungsmaterial | Material je Produktionslauf oder je Output-Einheit |
| M2 | Wartungsplan | Turnus nach Zeit, Laufstunden oder Zyklen; Hilfsmittel und Kosten |
| M3 | Wartungsprotokoll | durchgefuehrte Wartung, Ist-Kosten, Belege, naechster Termin |
| M4 | Belegungskalender | geplante Produktionsfenster, Wartungsfenster, Auslastung |
| M5 | Kalkulationsintegration | Kapazitaet, Zusatzstoffe und Wartungskosten in Rezeptur-Snapshot |

### Datenmodell-Skizze

```sql
CREATE TABLE dg_machine_capacity_profiles (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    work_center_id INT UNSIGNED NOT NULL,
    recipe_id INT UNSIGNED NULL,
    input_material_label VARCHAR(191) NOT NULL DEFAULT '',
    input_article_id INT UNSIGNED NULL,
    input_qty_per_hour DECIMAL(14, 4) NOT NULL DEFAULT 0,
    output_product_label VARCHAR(191) NOT NULL DEFAULT '',
    output_article_id INT UNSIGNED NULL,
    output_qty_per_hour DECIMAL(14, 4) NOT NULL DEFAULT 0,
    unit VARCHAR(50) NOT NULL DEFAULT '',
    yield_percent DECIMAL(6, 2) NOT NULL DEFAULT 100.00,
    valid_from DATE NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    notes VARCHAR(1000) NOT NULL DEFAULT '',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_machine_capacity_work_center (work_center_id),
    KEY idx_machine_capacity_recipe (recipe_id)
);

CREATE TABLE dg_recipe_auxiliary_materials (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    recipe_id INT UNSIGNED NOT NULL,
    article_id INT UNSIGNED NULL,
    material_label VARCHAR(191) NOT NULL DEFAULT '',
    role ENUM('packaging', 'additive', 'consumable', 'other') NOT NULL DEFAULT 'other',
    qty_per_output_unit DECIMAL(14, 4) NOT NULL DEFAULT 0,
    unit VARCHAR(50) NOT NULL DEFAULT '',
    cost_per_unit DECIMAL(14, 4) NOT NULL DEFAULT 0,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    PRIMARY KEY (id),
    KEY idx_recipe_aux_recipe (recipe_id)
);

CREATE TABLE dg_machine_maintenance_plans (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    work_center_id INT UNSIGNED NOT NULL,
    title VARCHAR(191) NOT NULL,
    interval_type ENUM('days', 'runtime_hours', 'cycles') NOT NULL,
    interval_value DECIMAL(14, 2) NOT NULL,
    planned_downtime_minutes INT UNSIGNED NOT NULL DEFAULT 0,
    helper_materials_json JSON NULL,
    estimated_material_cost DECIMAL(14, 2) NOT NULL DEFAULT 0.00,
    estimated_service_cost DECIMAL(14, 2) NOT NULL DEFAULT 0.00,
    next_due_date DATE NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_machine_maintenance_work_center (work_center_id)
);

CREATE TABLE dg_machine_maintenance_logs (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    maintenance_plan_id INT UNSIGNED NULL,
    work_center_id INT UNSIGNED NOT NULL,
    performed_at DATETIME NOT NULL,
    runtime_hours_at_service DECIMAL(14, 2) NULL,
    actual_material_cost DECIMAL(14, 2) NOT NULL DEFAULT 0.00,
    actual_service_cost DECIMAL(14, 2) NOT NULL DEFAULT 0.00,
    notes VARCHAR(1000) NOT NULL DEFAULT '',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_machine_maintenance_log_work_center (work_center_id),
    KEY idx_machine_maintenance_log_plan (maintenance_plan_id)
);
```

### Fachliche Grenzen

- Belegung und Wartung duerfen vorhandene Rezeptur-Snapshots nicht rueckwirkend veraendern.
- Wartungskosten zuerst als Kalkulationsbestandteil / Vorschlag, nicht automatisch als FiBu-Buchung.
- Verpackung/Zusatzstoffe koennen wie Material kalkuliert werden, sollten aber separat sichtbar bleiben.
- Wartungsbelege gehoeren in die normale Belegkette bzw. `storage/vouchers`, nicht in eine parallele Dateiablage.

---

## 6. Maschinenwissen + Akademie

### Zielbild

Wenn in der Werkhalle ein Fehlercode auftaucht, zeigt DG CRM:

- passendes Handbuch-PDF oder internes Dokument
- passendes 2- bis 5-Minuten-Akademie-Video
- betroffene Maschine / Arbeitsplatz
- optional betroffene Rezeptur oder Produktionsschritt

### MariaDB-Schema-Skizze

Das PDF-Schema nutzt UUID und PostgreSQL-Arrays. Für DG CRM wird es MariaDB-kompatibel:

```sql
CREATE TABLE dg_machine_knowledge_links (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    work_center_id INT UNSIGNED NULL,
    recipe_id INT UNSIGNED NULL,
    title VARCHAR(191) NOT NULL,
    content_type ENUM('manual_pdf', 'academy_module', 'external_url') NOT NULL,
    storage_path VARCHAR(500) NOT NULL DEFAULT '',
    academy_module_id INT UNSIGNED NULL,
    video_duration_seconds INT UNSIGNED NULL,
    error_codes_json JSON NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_machine_knowledge_work_center (work_center_id),
    KEY idx_machine_knowledge_recipe (recipe_id),
    KEY idx_machine_knowledge_academy_module (academy_module_id),
    CONSTRAINT fk_machine_knowledge_work_center
        FOREIGN KEY (work_center_id) REFERENCES dg_work_centers (id) ON DELETE SET NULL,
    CONSTRAINT fk_machine_knowledge_academy_module
        FOREIGN KEY (academy_module_id) REFERENCES dg_academy_modules (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### UX

- Menüpunkt bleibt bei **Rezeptur / Maschinen** oder später **Produktion**.
- Maschinendetail bekommt Tab **Wissen & Störungen**.
- Fehlercode-Suche: Eingabe `ERR_HYDRAULIK_404` → Trefferliste PDF/Video.
- Akademie-Regeln bleiben gültig: kurze Clips, echte CRM-Screens, VTT, einfache Sprache.

---

## 7. Priorität für DG CRM

| Priorität | Paket | Wann |
|-----------|-------|------|
| 1 | Maschinen-Belegungsplan: Kapazitaet, Output/h, Zusatzstoffe, Wartung | jetzt als frueher Maschinenblock |
| 2 | Maschinen-Gemeinkosten aus FiBu als **nicht buchungswirksamer Kalkulationsvorschlag** | nach Kapazitaets-/Wartungsgrundlage |
| 3 | Maschinenwissen mit Akademie-Verlinkung | jetzt/parallel möglich, klein umsetzbar |
| 4 | Lohn D0-A sauber spezifizieren | nach A/B/C-Fibu- und Hetzner-Basis |
| 5 | Lohn D0-A implementieren | vor ITSG-Automatik und Baulohn |
| 6 | ITSG / ELStAM / euBP / UV live | nach Hetzner, Zertifikaten und Testläufen |
| 7 | Baulohn / SOKA / Saison-KUG | nach Standardlohn, idealerweise nach SV-Automatik |

---

## 8. Nicht sofort bauen

- Kein neues ERP-Monolith-Modul neben Rezeptur/Lager/Buchhaltung.
- Keine Baulohn-Implementierung vor Normal-Lohn.
- Kein direkter SV-Versand ohne ITSG-Pfad.
- Kein automatischer FiBu-Buchungsroboter ohne Freigabe, AuditLog und Storno-/Korrekturkonzept.
- Kein PostgreSQL-Schema mit UUID/Arrays im MariaDB-CRM.
