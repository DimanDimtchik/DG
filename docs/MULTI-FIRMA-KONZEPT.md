# Multi-Firma / Umfirmierung — Produktkonzept

Stand: **2026-09-04** · Status: **Konzept** (noch nicht umgesetzt)  
Bezug: KDV (`docs/KDV-TODO.md`), Shop-Pakete (`shop/config/plans.php`), Buchhaltung, Lizenzserver

---

## 1. Problem

Kunden brauchen oft **mehr als eine rechtliche Firma** in einem Login:

| Fall | Beispiel |
|------|----------|
| **Umfirmierung unterjährig** | Einzelunternehmen → GmbH zum 15.07.; Alt und Neu laufen parallel bis Abschluss |
| **Umfirmierung zum WJ-Beginn** | Wechsel zum 01.01. |
| **Tochter / Schwester** | Holding + operative GmbH; zwei Gewerbe derselben Person |
| **Zweite Marke / Betrieb** | Separates Gewerbe mit eigener Steuernummer |

Heute: **1 CRM-Instanz = 1 DB = 1 Firma**. Firmendaten überschreiben reicht prüftechnisch nicht (GoBD, getrennte Bücher, getrennte Steuernummern).

---

## 2. Steuerrechtlicher Rahmen (kurz)

### Wann Bilanzierungspflicht entsteht

| Grundlage | Inhalt |
|-----------|--------|
| **§ 140 AO** | Abgeleitet aus anderen Gesetzen (v. a. HGB) — z. B. GmbH/UG/AG immer bilanzierungspflichtig |
| **§ 141 AO** | Originäre steuerliche Buchführungspflicht für **gewerbliche** Unternehmer (nicht Freiberufler), wenn FA feststellt: Umsatz **> 800.000 €**/Kalenderjahr **oder** Gewinn **> 80.000 €**/WJ — erst nach **Mitteilung** des FA |
| **§ 141 Abs. 2** | Pflicht beginnt mit dem **Wirtschaftsjahr nach Bekanntgabe** der Mitteilung (typisch nicht mitten im laufenden WJ) |
| **§ 141 Abs. 3** | Bei Betriebsübernahme im Ganzen geht die Buchführungspflicht **mit** (keine neue Mitteilung) |
| **Wahlrecht** | § 4 Abs. 1 vs. § 4 Abs. 3 EStG; freiwillige Bilanzierung bindet i. d. R. (oft 3 WJ); Umwandlung kann Wechselgrund sein |

### Unterjährige Umfirmierung (nicht nur 01.01.)

```
01.01 ──────── Stichtag (z. B. 15.07.) ──────── 31.12
   Alt: Rumpf-WJ bis Stichtag (EÜR oder Bilanz je nach Pflicht)
              Neu: ab Stichtag (oft GmbH → immer Bilanz)
```

Zusätzlich oft **Übergangsgewinn/-verlust** (Anpassung Zufluss/Abfluss ↔ Periodenabgrenzung).  
Software-Bedarf: **zwei Buchungskreise** (Rechtsträger), Stichtag, getrennte Abschlüsse — nicht „ein Jahr EÜR + ein Jahr Bilanz“ als einziges Modell.

---

## 3. Produktziel (UX)

Wie WordPress **Multisite**, aber steuerlich sauber:

- Oben links (Logo + Firmenname) → **Dropdown „Firma wechseln“**
- Ein Login (Organisations-Konto), mehrere Firmen-Slots
- Jede Firma: eigene Stammdaten, Bücher, Steuernummern, ggf. eigenes Paket

---

## 4. Architektur-Entscheidung

| Variante | Beschreibung | Bewertung |
|----------|--------------|-----------|
| **A — Verbundene Instanzen + Switcher** | Pro Firma eigene Instanz/DB (wie heutiges KDV); Org-Login wechselt Kontext | **Empfohlen** — GoBD-klar, Lizenz/Backup/Prüferpfad je Rechtsträger |
| **B — Multisite in einer DB** | `company_id` an allen Beleg-/Journal-Tabellen | Sehr invasiv, hohes Fehlerrisiko |
| **C — Hybrid** | Shared Users + getrennte Buchungs-DBs | Mehr Sonderlogik ohne klaren Gewinn |

**Festlegung Konzept:** Variante **A**.

Bestehende Bausteine nutzen:

- KDV-Provision / Kundeninstanzen
- Lizenzserver (`dg-user.ganz-soft.de`) — Key **pro Domain/Firma**
- Shop-Tarife Starter / Business / Premium

---

## 5. Datenmodell (Org + Firma)

### Organisation (Rechnungsempfänger / Login-Träger)

- `org_id`, Name, Rechnungsadresse, Stripe-Kunde
- Benutzer der Org (einmal einladen, Rechte **pro Firma**)

### Firma (Rechtsträger / Slot)

| Feld | Bedeutung |
|------|-----------|
| `company_id` / Instanz-Domain | Technische Identität |
| `legal_name`, `company_type` | Rechtsform (GmbH, Einzelunternehmen, …) |
| `tax_id` / Steuernummer / USt-IdNr. | Pro Firma |
| `gewinnermittlung` | `euer` \| `bilanz` (Steuerung Hinweise/Export/Jahresabschluss) |
| `relation` | `standalone` \| `tochter` \| `schwester` \| `nachfolger` \| `vorgaenger` |
| `related_company_id` | Verknüpfung (z. B. Vorgänger bei Umfirmierung) |
| `effective_from` / `effective_to` | Stichtage (Rumpf-WJ / Archiv) |
| `tariff` | `basic` / `business` / `enterprise` (Shop: starter/business/premium) |
| `status` | `active` \| `archive_readonly` \| `closed` |

### Firmendaten-Historie (später / prüfrelevant)

Stichtagsgültige Änderungen: Name, Rechtsform, Steuernummer — für Impressum und Betriebsprüfung.

---

## 6. Funktionen

### MVP

1. **Firmen-Switcher** im CRM-Header (Logo/Name)
2. Pro Firma: eigene DB-Instanz, Stammdaten, WJ, Journal, Belege, Bank
3. Beziehungstypen inkl. Umfirmierung (`vorgaenger`/`nachfolger`)
4. **Umfirmierungs-Assistent:** Stichtag setzen, Alt → Archiv/Read-only ab Stichtag, Neu anlegen, Checkliste Übergangsgewinn (Hinweis; Buchung manuell oder halbautomatisch)
5. Getrennte DATEV-Mandantennummern
6. Benutzer: Org-weit einladen, ACL pro Firma
7. Lizenz/Paket **pro Firma** (nicht nur pro Login)

### Später

- Optional geteilte Kontakte (Herkunftskennzeichen)
- Intercompany-Belege
- Konzern-Übersicht (ohne steuerliche Konsolidierung vorzutäuschen)
- Bericht „Rumpfwirtschaftsjahr / Umfirmierung“
- Automatische Überleitungsbuchungen (Übergangsgewinn)

---

## 7. Preis & Pakete

Aktuelle Listenpreise (netto/Monat, zzgl. USt.): **Starter 29 € · Business 49 € · Premium 89 €** (`shop/config/plans.php`).

### Regeln (Konzept)

| Situation | Preislogik |
|-----------|------------|
| **Erste aktive Firma** | Voller Tarif |
| **Weitere aktive Firma** (Tochter, zweites Gewerbe) | **−20 %** auf den Listenpreis des gewählten Pakets der Zusatzfirma |
| **Paketwahl** | Pro Firma frei (z. B. Premium + Starter) — Funktionsumfang folgt dem **jeweiligen** Tarif |
| **Umfirmierung** | **Kein** zweites Vollabo für denselben wirtschaftlichen Betrieb in der Übergangsphase: 1 aktives Paket für die Nachfolger-Firma; Vorgänger als **Archiv/Read-only** zeitlich begrenzt (Vorschlag: 3–12 Monate) **gratis** oder Pauschale **9–15 €**/Monat |
| **3.+ Firma** | Weiter −20 % oder Staffel −25 % / −30 % (noch festzulegen) |
| **Jahresabo** | Bestehender Jahresrabatt + Zweitfirma-Rabatt kombinierbar |
| **Voraussetzung Rabatt** | Gleicher Rechnungsempfänger / verknüpfte Org in KDV |

### Warum nicht pauschal 20 % auch bei Umfirmierung?

Unterjährig existieren zwei Rechtsträger, aber oft nur **ein** laufender Betrieb. Doppeltes Vollabo wirkt ungerecht und treibt Kunden aus dem System. Archiv-Slot + ein aktives Paket ist prüf- und verkaufsseitig sauberer; 20 %-Zweitpaket bleibt für **dauerhaft parallele** Firmen.

### AGB / DSGVO (Hinweis)

Jede Firma = eigener Datenkreis; AV-Vertrag / Auftragsverarbeitung klar der Org zuordnen; Löschfristen pro Firma.

---

## 8. Umfirmierungs-Assistent (Fachablauf)

1. Aktive Firma wählen → „Umfirmierung starten“
2. Stichtag, neue Rechtsform, neuer Name, neue Steuernummer (sobald bekannt)
3. System legt **Nachfolger-Slot** an (neue Instanz oder vorbereitete DB)
4. Optional: Stammdaten/Nummernkreise/Kontenrahmen übernehmen (keine stillen Buchungsübernahmen ohne Beleg)
5. Checkliste: Eröffnungsbilanz Neu, Abschluss Alt, Übergangsgewinn, Bankkonten, Verträge, USt, Impressum/Website
6. Ab Stichtag: Buchungen nur noch in der jeweils gültigen Firma; Alt read-only nach Frist
7. Switcher zeigt beide (Alt als „Archiv“) bis `effective_to`

---

## 9. Abgrenzung / Nicht-Ziele (MVP)

- Keine steuerliche Konzernkonsolidierung
- Kein automatischer Rechtsformwechsel ohne Nutzerentscheidung
- Kein Vermischen von Journalen zweier Rechtsträger
- ELSTER/ERiC bleibt pro Firma (Zertifikat/Steuernummer)

---

## 10. Offene Entscheidungen

- [ ] Archiv-Dauer und Archiv-Preis (gratis vs. 9–15 €) final
- [ ] Staffel ab 3. Firma
- [ ] Ob Website/Domain fest an eine Firma gebunden ist oder Org-weit umschaltbar
- [ ] Shared Contacts: ja/nein im MVP
- [ ] Technische Provision: neue Subdomain vs. Pfad vs. bestehendes KDV-Domain-Modell

---

## 11. Umsetzungsphasen (grob)

| Phase | Inhalt |
|-------|--------|
| **0** | Dieses Konzept · KDV-Datenmodell Org↔Firma skizzieren |
| **1** | Org-Login + Switcher + verknüpfte Instanzen (manuell provisioniert) |
| **2** | Shop/KDV: Zweitfirma −20 %, Umfirmierungs-Archiv-Slot |
| **3** | Umfirmierungs-Assistent + Gewinnermittlungsart-Stammdatum |
| **4** | Historie Firmendaten, Berichte Rumpf-WJ, optionale Shared Contacts |

---

## 12. Referenzen

- § 140, § 141 AO  
- § 4 Abs. 1 / Abs. 3 EStG  
- `docs/KDV-TODO.md`, `docs/SHOP-TODO.md`, `docs/TODOS.md`  
- `shop/config/plans.php`
