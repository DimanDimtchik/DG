# ZUGFeRD-/Factur-X-Ausgang — Entscheidung (PA5b)

> Stand: **2026-11-08** · Mini-ID **PA5b** · Fach: [`IQ-STROM-HETZNER-TODO.md`](IQ-STROM-HETZNER-TODO.md) A5 / D2  
> **Kein Code in diesem Ticket.** Factur-X-Einbettung = späteres Backlog.

---

## Entscheidung (verbindlich)

| Frage | Antwort |
|-------|---------|
| Was liefert DG CRM heute im Ausgang? | **PDF** + separates **XRechnung-XML** (UBL, EN-16931-Profil pragmatisch) als Mail-Anhänge |
| Reicht das als E-Rechnung (strukturiert)? | **Ja**, solange das **XML mitgeht** — XRechnung ist ein anerkanntes Format |
| Brauchen wir jetzt eingebettetes ZUGFeRD/Factur-X? | **Nein** — Komfort/Produkt, kein Blocker für Phase A |
| Bis wann gilt die Entscheidung? | Bis Umsetzung des D2-Tickets „Factur-X eingebettet“; spätestens vor Ende der gesetzlichen Übergangsfristen prüfen (siehe unten) |
| Was ist **nicht** genug? | **Nur PDF** ohne strukturiertes XML = „sonstige Rechnung“ (Übergang / Zustimmung Empfänger) |

**Kurz:** Bewusst **PDF + XRechnung-XML** behalten. **Kein** Factur-X-/ZUGFeRD-PDF-Hybrid in Phase A.

---

## Ist-Stand Code (Referenz, keine Änderung)

| Baustein | Rolle |
|----------|--------|
| `VoucherDocumentPdfService` | Kunden-PDF |
| `VoucherEInvoiceXmlService` | XRechnung-XML (Rechnungen / Abschlag / Schluss, nicht Entwurf) |
| `VoucherMailAttachmentBuilder` | Mail: PDF immer; XML wenn `supports()` |

§13b-/Reverse-Charge-Felder im XML = **PA5c ✅** (`VoucherEInvoiceXmlService`: Kategorie AE, BT-120/121).

---

## Rechtlicher Rahmen (Orientierung, kein Gutachten)

Quelle u. a. BMF-FAQ E-Rechnung (Wachstumschancengesetz / § 14 UStG):

| Ab | Was |
|----|-----|
| **01.01.2025** | Empfangspflicht E-Rechnung (B2B inland); Ausstellung mit Übergangsregeln |
| **bis 31.12.2026** | Aussteller dürfen statt E-Rechnung noch „sonstige Rechnung“ wählen (Papier / PDF mit Zustimmung) |
| **bis 31.12.2027** | Zusätzlich bei Vorjahresumsatz **≤ 800.000 €** weiterhin sonstige Rechnung möglich |
| **ab 01.01.2028** | Für die übrigen inländischen B2B-Umsätze Versandpflicht E-Rechnung |

Anerkannte Wege u. a.: **XRechnung**, **ZUGFeRD ab 2.0.1** (geeignetes Profil).  
Einbettung im PDF (ZUGFeRD/Factur-X) ist **nicht** Voraussetzung, wenn ein strukturiertes XML (XRechnung) übermittelt wird.

---

## Ticket / Backlog (Factur-X)

| ID | Inhalt | Wann |
|----|--------|------|
| **D2** in [`IQ-STROM-HETZNER-TODO.md`](IQ-STROM-HETZNER-TODO.md) | ZUGFeRD/Factur-X **eingebettet** im Ausgangs-PDF (ein Datei-Hybrid statt PDF+XML-Anhang) | Nach Phase A; ideal vor Ende Übergangsfrist / wenn Kunden Hybrid verlangen |
| **PA5c** | XRechnung §13b Reverse-Charge BT-Felder prüfen | Phase A, eigener Chat |

Akzeptanzkriterien D2 (später, **kein** Code jetzt):

1. Hybrid-PDF enthält EN-16931-taugliches XML (Profil festlegen: z. B. EN 16931 / XRechnung-kompatibel).
2. Bestehender reiner XRechnung-XML-Anhang darf parallel bleiben oder abgelöst werden (Entscheidung im D2-Chat).
3. Selftest/Stichprobe: XML aus PDF extrahierbar; Stichprobe Portal/Validator.
4. Kein Tenant-Hardcode; Firmendaten nur aus Instanz-Settings.

---

## Nicht-Ziele (PA5b)

- Kein Factur-X-/PDF-Hybrid-Code
- Kein Peppol-Netzwerk
- Kein Umbau `VoucherEInvoiceXmlService`
- Keine Empfangs-Inbox für eingehende E-Rechnungen (eigenes Thema)

---

## Verweise

| Datei | Rolle |
|-------|--------|
| [`FIBU-VERKAUFSREIFE.md`](FIBU-VERKAUFSREIFE.md) | Verkaufsblocker / E-Rechnung-Zeile |
| [`PHASE-A-REST-PLAN.md`](PHASE-A-REST-PLAN.md) | PA5b / PA5c |
| [`IQ-STROM-HETZNER-TODO.md`](IQ-STROM-HETZNER-TODO.md) | A5 Entscheidung · D2 Umsetzung |
