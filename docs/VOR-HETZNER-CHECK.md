# Vor Hetzner — CRM-Grundcheck

Stand: **2026-09-17** · Ziel: vor Server-Umzug Architektur, Assets und Rechts-/Steuerpflichten klären  
Bezug: Shared-Core-Idee · [`MULTI-FIRMA-KONZEPT.md`](MULTI-FIRMA-KONZEPT.md) · [`BELEGKETTE-DARSTELLUNG.md`](BELEGKETTE-DARSTELLUNG.md)

---

## 1. Architektur / Vereinfachungen

| Thema | Ist | Empfehlung vor/bei Hetzner |
|-------|-----|----------------------------|
| Code-Verteilung | `sync-crm-from-master.sh` kopiert ganzen CRM-Baum | **Shared Core** + Kundenordner nur Config/Uploads/Logs |
| `index.php` | Sehr groß (Routing + POST) | Schrittweise Router/Controller auslagern (nicht Blocker für Umzug) |
| Doppelte Logik | Teilweise Settings + Module | Vor Umzug nur **kritische** Dubletten; kein Big-Bang-Refactor |
| Shop / KDV / Plugins | Getrennt | Getrennt lassen |
| DB pro Instanz | Ja | Beibehalten; nie Shared-DB |

**Priorität Umzug:** Ordnerlayout + Deploy-Pipeline zuerst. Refactor PHP nur, wenn es Deploy/Shared-Core erleichtert.

---

## 2. CSS / JS — Minimierung

| Asset | Beobachtung | Maßnahme |
|-------|-------------|----------|
| `assets/css/dg.css` | Groß; `dg.min.css` existiert, Layout lädt oft die Quelle | Build-Schritt: bei Deploy minifizieren + `Asset` wählt `.min` in Produktion |
| Viele Einzel-JS | Seite lädt nur bedarfsweise (gut) | Beibehalten; Bundle nur wo sinnvoll (z. B. Belege) |
| Vendor bereits `.min` | cropper, JsBarcode, … | Unverändert |
| Kein einheitlicher Build | Manuell | Optional: `bin/minify-assets.sh` (cssnano / terser) im Deploy |

**Erwartung:** spürbar weniger Transfer, aber kein Ersatz für Shared Core (Platte). Minimierung = Performance, Shared Core = Speicher/Deploy.

**Nicht priorisieren:** aggressives Bundling aller Seiten-JS (erschwert Debugging ohne großen Gewinn).

---

## 3. Gesetzliche / steuerliche Bestimmungen (Checkliste)

Perspektive: GoBD · UStG · Belegpflicht · Datenschutz · Handelsregister-Angaben.

### Buchhaltung / Belege

| Anforderung | Status (Kurz) | Lücke / nächster Schritt |
|-------------|---------------|--------------------------|
| Belegkette nachvollziehbar | ✅ Kette + Status | Auto-AB aus Mail noch offen |
| Keine Buchung vor Rechnung (Angebot/AB/LS) | ✅ | — |
| Rechnungsangaben § 14 UStG | ✅ Pflichtzeilen/Logo/Steuernr. | Stichprobe Druck je Dokumentart |
| Netto / USt je Satz / Brutto | ✅ Summentabelle | Test gemischte Sätze 7/19/0 |
| § 19 Kleinunternehmer-Hinweis | ✅ Zeitraum + Auto-Text | Produktiv aktivieren wenn zutreffend |
| Skonto / Mahnung / Teilzahlung | ✅ | Smoke-Test |
| Unveränderbarkeit / Änderungsnachweis | ⚠️ Speichern überschreibt | GoBD: Änderungshistorie / Storno-Konzept prüfen |
| Aufbewahrung Belegdateien | ✅ Storage pro Instanz | Backup-Konzept Hetzner |
| DATEV / UStVA / ELSTER | ✅ / ELSTER live blockiert | Nach Hetzner ERiC |

### Datenschutz / Website

| Anforderung | Status | Lücke |
|-------------|--------|-------|
| Rechtstexte (Impressum, DSGVO, AGB …) | ✅ Mehrprodukt | Inhalte je Instanz prüfen |
| Wartungsmodus + Kontakt | ✅ | — |
| Auftragsverarbeitung / TOMs | 📄 organisatorisch | Mit Hetzner-AVV abstimmen |
| Löschkonzepte (Mitarbeiterdaten-Cron) | ✅ teilweise | Vollständige Übersicht Retention |

### Sonstiges

| Thema | Status |
|-------|--------|
| Stripe Shop live | bewusst **nicht** |
| LDAP | Phase 2 nach Hetzner |
| Multi-Firma | Phase 0; Switcher später |

---

## 4. Reihenfolge (Vorschlag)

1. **Jetzt:** Deploy/Test Belegdarstellung + AB-Druck (läuft)
2. **Vor Umzug:** Shared-Core-Ordnerplan + Backup/Restore-Test
3. **Vor Umzug:** Asset-Minify im Deploy (klein, risikoarm)
4. **Vor Umzug:** GoBD-Stichprobe (Änderung/Storno, Beleg-PDF, USt-Aufteilung)
5. **Beim Umzug:** Domains, DB-Restore, ELSTER/ERiC Linux
6. **Nach Umzug:** LDAP, Auto-AB-Mail, Material-Anzahlung

---

## 5. Bewusst nicht vor dem Umzug

- Großes UI-Redesign
- Komplettes Aufteilen von `index.php`
- Live-ELSTER auf All-Inkl erzwingen
