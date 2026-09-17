# Belegkette — Kundendarstellung & Druck

Stand: **2026-09-17** · Status: **Phase A–D angelegt** · Auto-AB aus Mail-Antwort: **noch offen**  
Bezug: Lexware-Office-Funktionen als Orientierung (nicht Layout-Kopie) · eigener DG-Style

---

## Ziel

Kundenfähige Dokumente (Angebot → … → Rechnung/Gutschrift): A4-ähnlich, klare Summen (Netto / USt / Brutto), editierbare Texte vor/nach Positionen. Interne CRM-Hinweise (Belegkette, „ohne Buchungswirkung“) **nicht** im Kunden-PDF.

---

## Auftragsbestätigung — Automatik vs. manuell

| Weg | Status | Vermerk auf Dokument |
|-----|--------|----------------------|
| **Manuell** („Folgebeleg“ aus Angebot) | ✅ vorhanden | Nach Speichern → Druck; Unterschriften Auftraggeber/-nehmer; Vermerk wer/wann |
| **Automatisch** bei Annahme-Antwort auf Angebots-Mail | ✅ Migration 083 · `OfferAcceptanceMailService` | wann, per E-Mail, wer, mail_log_id (ohne Nassunterschrift) |

Heute: Angebot per Mail versenden (SMTP) speichert `voucher_id` + Message-ID. Antwort auf denselben Thread (Inbound-Webhook mit In-Reply-To) erzeugt automatisch eine Auftragsbestätigung inkl. Annahme-Vermerk. Ablehnungstexte (z. B. „ablehnen“) werden ignoriert.

---

## Entscheidung: wo einstellen?

| Thema | Ort |
|-------|-----|
| Nummern | Einstellungen → **Nummernkreise** |
| Darstellungstexte, Anzahlung, § 19 | Einstellungen → Buchhaltung → **Belegdarstellung** |
| Pro Beleg überschreiben | Belegformular: Intro / Footer / „gültig bis“ |

---

## Lexware-Funktionen (Orientierung)

| Funktion | Lexware | DG-Umsetzung |
|----------|---------|--------------|
| Drucklayout Einstellungen | Einstellungen → Drucklayout | **Belegdarstellung** + Firmen-/Logo-Daten |
| Kopf/Fuß, Logo, Seitenzahl | Layout-Designer | Logo/Firma + A4-Vorschau mit Seitenmarkierung |
| Einleitung / Nachbemerkung | je Beleg | Vorlagen in Belegdarstellung + Felder am Beleg |
| Gültig bis (Angebot) | Pflichtfeld | Feld „gültig bis“ (= delivery_date) + `{valid_until}` |
| Summen Netto+USt+Brutto | transparent | Summentabelle nach Positionen |
| Anzahlung / Abschlag | Zahlungsbedingungen | Belegdarstellung: %, fest, materialbasiert |
| § 19 Kleinunternehmer | Hinweistexte | Zeitraum + vorzeitiger Abbruch + Auto-Hinweis |

---

## Phasen

| Phase | Inhalt | Status |
|-------|--------|--------|
| **A** | Kunden-Druck bereinigen; Intro/Footer Kette; Summentabelle; A4-Vorschau | **erledigt** |
| **B** | Einstellungen „Belegdarstellung“ (Vorlagen, Gültigkeitstage) | **erledigt** |
| **C** | Anzahlungsregeln (%, fest, Material-Anzeige) | **UI + Drucktext** · Materialberechnung aus Lager folgt |
| **D** | Kleinunternehmer § 19 (Zeitraum, Abbruch, Auto-Hinweis) | **erledigt** (Hinweis auf buchbaren Belegen) |

---

## Regeln (verbindlich)

1. **Druck/PDF/E-Mail** = Kundenansicht · Belegkette nur intern (`no-print` / `show_chain=1`)
2. Angebot ≠ Rechnung inhaltlich vor allem durch **Intro/Footer** (und Meta „gültig bis“)
3. Summen: **Netto** + **USt je Satz** = **Brutto** (transparent)
4. „Ohne Buchungs-/USt-Wirkung“ nur intern, **nicht druckbar**
5. Nummernkreise bleiben Nummern — **Darstellung** unter Belegdarstellung

---

## Code

- `DocumentPresentationSettings` · `views/settings/tab-belegdarstellung.php`
- `VoucherDocumentPrintService` · `views/print/voucher-document.php`
- `AccountingPrintService` (A4-Sheet, kein doppelter Titel)
