> **Port auf master 2026-10-26** — Migrationen **117–119** (nicht Cloud 114–116). Schema: `dg_gift_vouchers` (`face_value_gross`, `remaining_gross`, `issued_voucher_id`), Einlösungen in `dg_voucher_gift_redemptions`, Belegzeilen `dg_voucher_gift_lines`. Default Verbindlichkeit **1796** (nicht 1790), Aufwand **4780**.

# Reklamation & Gutschein — Spec (Source of Truth)

Stand: **2026-10-26** · Status: **RG0–RG6 erledigt** · Zielsystem: DG CRM (Buchhaltung + Katalog)

> Agent-Regel: Bei Arbeit an diesem Modul **nur diese Spec** und die in der jeweiligen Phase genannten Dateien lesen. Kein Scope außerhalb der Phase.

---

## 1. Ziel

1. **Mehrzweck-Gutscheine** (Geschenk): Verkauf ohne USt auf Verbindlichkeit; Einlösung über Rechnung/Kasse; USt erst über eingelöste Artikel/Leistungen.
2. **Reklamation**: Annahme → Beurteilung → Stellungnahme → Maßnahmen; bei Schuld Unternehmen optional Entschuldigungs-Gutschein.
3. **Garantie/Gewährleistung** an Artikel/Leistungen für Klarheit in Reklamation.

---

## 2. Fachliche Leitplanken (Steuer / GoBD)

1. Geschenk-Gutschein = **Mehrzweckgutschein** (UStG § 3 Abs. 13–15): Verkauf **ohne USt**, Verbindlichkeit; USt bei Einlösung.
2. Kundengutschrift-Belegart (`credit`) bleibt **getrennt** — Gutschein-Einlösung ist keine Kundengutschrift.
3. Entschuldigungs-Gutschein: Aufwand **Garantieleistung** an Verbindlichkeit; kein Umsatz.
4. Belegkette/Snapshots: bereits gebuchte Einlösungen nicht still rückgängig ohne Storno-Pfad.
5. Konten einstellbar (`GiftVoucherSettings`): Default Verbindlichkeit `1796`, Aufwand Entschuldigung `4780` (falls nicht im Rahmen: manuell setzen).

---

## 3. Datenmodell

| Tabelle / Spalte | Zweck |
|------------------|--------|
| `dg_gift_vouchers` | Code, Betrag, Restwert, Typ `sale`/`apology`, Status, Kontakt, complaint_id, issued_voucher_id |
| `dg_voucher_gift_redemptions` | Einlösung je Beleg/Betrag |
| `dg_voucher_gift_lines` | Belegzeilen Verkauf/Einlösung (zwischen Positionen und Freitext) |
| `dg_complaints` | Reklamation + Pipeline + Schuld |
| `dg_complaint_events` | Ereignisprotokoll |
| `dg_calendar_articles.warranty_*` | `warranty_kind`, `warranty_months`, `warranty_text` |

---

## 4. UX

1. Neuer Beleg: nach Artikel/Leistungen → **Gutscheine** → Freitext.
2. Module: `gutscheine`, `reklamation`, Flyer `website-gutschein-flyer`.
3. Artikel: Garantie vs. Gewährleistung (Kurzhinweis) + Freitext.
4. Flyer: schlicht A6/A5, Logo, Code, Betrag, Gültigkeit.

---

## 5. Roadmap

| Phase | Lieferobjekt |
|-------|----------------|
| **RG0** ✅ | Spec + Flags `features.gutschein` / `features.reklamation` + Menü |
| **RG1** ✅ | Garantie/Gewährleistung an Artikeln (`117`) |
| **RG2** ✅ | Gutschein-Stammdaten + Mehrzweck-Verkauf (`118`) |
| **RG3** ✅ | Beleg-Einlösung + Zahlung `gift_voucher` |
| **RG4** ✅ | Reklamation Annahme–Maßnahmen (`119`) |
| **RG5** ✅ | Entschuldigungs-Gutschein (Garantieleistung) |
| **RG6** ✅ | Gutschein-Flyer unter Formulare |

**Chat-Vorlage:**

```text
Scope: Reklamation/Gutschein RG3 laut docs/REKLAMATION-GUTSCHEIN.md
Nur: … Kein Flyer. Kein Deploy außer ich sage es.
```

---

## 6. Nicht-Ziele (RG0–RG6)

- Einzweckgutscheine
- Online-Shop / Stripe-Einlösung
- Automatischer Verfall-Job
- Umbau Kundengutschrift-Belegart
