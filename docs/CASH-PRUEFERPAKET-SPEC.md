# Registerkasse — Spec Prüferpaket (K18a)

> Verbindlich für DG CRM Registerkasse / Kassen-Nachschau.  
> Bündelt **DSFinV-K** (K17) + **TSE-Export/TAR** (K18b) + Begleitmeta.  
> Download-UI: **K18c**. Echte Provider-TAR: **K18b** (bewusst später).  
> Nicht verwechseln mit dem FiBu-**GoBD-Prüferexport** (`GobdAuditExportService`).

---

## 1. Zweck

Bei Kassen-Nachschau / Betriebsprüfung muss das Kassensystem den vorgeschriebenen Datenkranz **plus** die TSE-Rohdaten in einem nachvollziehbaren Paket liefern (§ 146a AO, KassenSichV, GoBD Datenträgerüberlassung).

Das **Prüferpaket** ist der DG-Container dafür: ein ZIP mit klarer Ordnerstruktur, Manifest und Checksums — nicht ein eigenes „Kassendatenformat“.

**Abgrenzung**

| Thema | Hier (K18) | Anderswo |
|-------|------------|----------|
| DSFinV-K CSV + Index | Inhalt Ordner `dsfinvk/` | Spec/Stub/Befüllung: K17a–c |
| TSE-TAR / Provider-Rohexport | Inhalt Ordner `tse/` | Abruf API: K18b |
| Bon-Journal CSV (intern) | optional Begleitdatei | K16b |
| FiBu-Journal / Belegindex | **nicht** im Kassen-Prüferpaket | `GobdAuditExportService` |
| Verfahrensdoku-Text | optional Kurz-README / Verweis | K21 |

---

## 2. Lieferform

| Bestandteil | Pflicht | Bemerkung |
|-------------|---------|-----------|
| Äußerer Container **ZIP** | ja | ein Download, UTF-8-Dateinamen |
| Ordner `dsfinvk/` | ja | vollständiger DSFinV-K-Satz laut [`DSFINVK-SPEC.md`](DSFINVK-SPEC.md) (20 CSV + `index.xml` + DTD) |
| Ordner `tse/` | ja* | mindestens eine TAR-/Export-Datei **oder** dokumentierter Stub/Hinweis, wenn Provider-Export fehlt (siehe §5) |
| `manifest.json` | ja | Metadaten + Dateiliste + SHA-256 je Datei |
| `README.txt` | ja | Kurz: Zeitraum, Kasse(n), was enthalten / was Stub |
| Bon-Journal CSV | empfohlen | `journal/cash_receipts_journal.csv` (K16b-Format) |
| Audit-Auszug | optional | gefilterte `dg_cash_audit_log`-Zeilen als CSV (Ausfall, Export, Storno) |

\* „ja“ = Ordner immer vorhanden. Fehlt der echte TAR (vor K18b / Mock), liegt dort eine **explizite** Platzhalterdatei `tse/EXPORT_STATUS.txt` — niemals eine erfundene Signatur-TAR.

**Nicht** im Prüferpaket:

- FiBu-Ledger, DATEV, UStVA
- Klartext-Secrets (API-Keys, Zertifikat-Passwörter)
- Entwürfe (`status=draft`)
- Kundenstammdaten über das hinaus, was DSFinV-K ohnehin vorsieht

---

## 3. Verzeichnisstruktur (verbindlich)

```text
DG_Cash_Prueferpaket_<von>_<bis>[_<kasse>].zip
├── README.txt
├── manifest.json
├── dsfinvk/
│   ├── index.xml
│   ├── gdpdu-01-09-2004.dtd   (o. ä.)
│   ├── cashpointclosing.csv
│   ├── … (alle 20 CSV laut K17)
│   └── transactions_tse.csv
├── tse/
│   ├── <provider>_export_<von>_<bis>.tar   # Ziel nach K18b
│   └── EXPORT_STATUS.txt                  # immer; Status/Fehlgrund
└── journal/                               # empfohlen
    └── cash_receipts_journal.csv
```

Zusätzliche Dateien nur unter `extra/` und nur mit Eintrag in `manifest.json`.

---

## 4. Dateinamen

| Artefakt | Muster |
|----------|--------|
| Äußeres ZIP | `DG_Cash_Prueferpaket_YYYY-MM-DD_YYYY-MM-DD.zip` · optional Suffix `_` + `registers.code` |
| DSFinV-Dateien | unverändert englische Spec-Namen (K17) — **nicht** umbenennen |
| TSE-TAR | `{provider}_tse_{tssShort}_{von}_{bis}.tar` z. B. `fiskaly_tse_a1b2_2026-01-01_2026-01-31.tar` |
| Journal | `cash_receipts_journal.csv` |
| Status | `EXPORT_STATUS.txt` (Plaintext, UTF-8) |

`export_type` in `dg_cash_exports`: **`pruefer`** für das Gesamtpaket.  
Einzelabrufe: `dsfinvk` · `tse_tar` · `csv_journal` (bereits K1i).

---

## 5. `tse/` — Inhalt und Statusregeln

### 5.1 Ziel (nach K18b)

| Datei | Bedeutung |
|-------|-----------|
| Provider-TAR (o. ä.) | Rohdaten der TSE laut Anbieter-API (`TseProviderInterface::exportData`) |
| `EXPORT_STATUS.txt` | `status=ok` · Zeitraum · Provider · Checksum der TAR · TSS-/Client-IDs (ohne Secrets) |

`exportData`-Request (Plan K2a / Interface):

| Request | Result |
|---------|--------|
| `date_from`, `date_to` | `ok` |
| `format` = `tar` (Default) | `file_bytes` **oder** `download_ref` |
| optional `register_id` / Assignment-Kontext | `checksum`, `message` |

Mehrere Kassen / mehrere TSE-Assignments im Zeitraum → **eine TAR je Assignment** (oder eine kombinierte, wenn der Provider das so liefert) + alle in `manifest.json` und in `EXPORT_STATUS.txt` aufgeführt.

### 5.2 Bis K18b / Mock

| Datei | Inhalt |
|-------|--------|
| kein Fake-TAR | — |
| `EXPORT_STATUS.txt` | `status=stub` oder `status=unavailable` · Grund (`provider_not_implemented` · `mock` · `outage`) · Hinweis „DSFinV-K Signaturfelder siehe transactions_tse.csv“ |

Mock-Provider darf **keinen** „echten“ TAR vortäuschen. Signaturwerte in DSFinV bleiben Mock-Kennzeichnung (bereits K2b).

### 5.3 TSE-Ausfall im Zeitraum (K14)

- Historische Ausfall-Flags an Bons bleiben in DSFinV / Journal sichtbar.
- TAR enthält nur, was die TSE/Cloud tatsächlich gespeichert hat — Lücken **nicht** auffüllen.
- `EXPORT_STATUS.txt` nennt `outage_receipts_present=yes|no` (Ableitung aus `dg_cash_receipts` / Audit), ohne die Lücke zu kaschieren.

---

## 6. `manifest.json` — Kernfelder

```json
{
  "product": "DG CRM",
  "export_type": "pruefer",
  "spec": "CASH-PRUEFERPAKET-SPEC K18a",
  "generated_at": "ISO-8601",
  "date_from": "YYYY-MM-DD",
  "date_to": "YYYY-MM-DD",
  "location_id": null,
  "register_ids": [],
  "company_label": "(aus Instanz-Settings, kein Hardcode)",
  "dsfinvk": {
    "spec_version": "2.3",
    "status": "stub|filled",
    "files": [{ "name": "dsfinvk/transactions.csv", "sha256": "…" }]
  },
  "tse": {
    "status": "ok|stub|unavailable",
    "provider": "mock|fiskaly|…",
    "files": [{ "name": "tse/….tar", "sha256": "…" }]
  },
  "files": [{ "name": "README.txt", "sha256": "…" }]
}
```

SHA-256 über den **Dateiinhalt** (wie FiBu-GoBD-Manifest). Gesamtpaket-Checksum zusätzlich in `dg_cash_exports.file_checksum` beim Speichern (K18c).

---

## 7. Filter und Scope

| Parameter | Regel |
|-----------|--------|
| Zeitraum | `date_from` / `date_to` inklusiv, Geschäftstag der Bons / Closings |
| Kasse | optional eine `register_id`; sonst alle aktiven Kassen der Instanz (oder gefilterte Location) |
| Status Bons | nur `finalized` (+ zugehörige Storno-Bons); keine Drafts |
| Closings | alle Register-Tagesabschlüsse, die den Zeitraum schneiden |

Leerer Zeitraum (keine Bons): Paket trotzdem gültig — DSFinV mit Kopfzeilen (K17), `tse/` Stub/leer laut Status, Journal 0 Datenzeilen. Kein Fehler nur wegen „keine Umsätze“.

---

## 8. Audit / Speicherung

| Schritt | Pflicht |
|---------|---------|
| Job in `dg_cash_exports` | `export_type=pruefer`, Status `pending`→`done`/`failed` |
| Datei unter `storage/` | relativer `file_path`, SHA-256 |
| `dg_cash_audit_log` | `event_type=export.pruefer` (bei Erfolg/Fehler) |
| Download | nur berechtigte Admin-/Buchhaltungsrolle (K18c konkretisiert UI) |

Kein stilles Überschreiben früherer Exporte: neuer Job = neue Datei (Zeitstempel im Namen oder eigene `id`-Unterordner).

---

## 9. Abgrenzung Umsetzung

| Mini-ID | Was |
|---------|-----|
| **K18a** | dieses Spec-Doc ✅ |
| **K18b** | 🔴 `exportData` live (Fiskaly o. ä.) → echte TAR-Bytes; bewusst später |
| **K18c** | 🟡 ✅ `CashPrueferpaketExport` + Bon-Journal-UI; Stub-`tse/` + DSFinV-Stub |

**Empfohlene Reihenfolge:** K18a ✅ → K18c ✅ → K18b (Provider füllt `tse/`).

**Nicht tun**

- Prüferpaket mit FiBu-GoBD-ZIP vermischen oder unter demselben Menüpunkt ohne klare Trennung anbieten
- TAR-Inhalt erfinden oder Signaturen nachbauen
- DSFinV-Dateinamen ändern
- Secrets oder Live-Zertifikate ins ZIP legen
- Tenant-/Kundenfirmen im Spec oder in Default-README hardcoden

---

## 10. Referenz

- DSFinV-K: [`DSFINVK-SPEC.md`](DSFINVK-SPEC.md)  
- Plan / Tabellen: [`REGISTERKASSE-TSE-PLAN.md`](REGISTERKASSE-TSE-PLAN.md) (K1i `dg_cash_exports`, K2a `exportData`, K17, K18)  
- Interface: `TseProviderInterface::exportData`  
- Rechtsrahmen (Orientierung): § 146a AO, KassenSichV, GoBD — konkrete Checker-Anforderungen folgen der amtlichen DSFinV-K- und TSE-Dokumentation des Anbieters
