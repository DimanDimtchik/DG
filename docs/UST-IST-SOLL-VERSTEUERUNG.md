# Soll- / Ist-Versteuerung (USt)

> Stand: 2026-11-01 · Code: `VatTaxationSettings`, `UstvaReportService`, `LedgerPostingService`

## Fachregel

| Regime | Ausgangs-USt (KZ 81/86/83/35) | Vorsteuer | §13b |
|--------|------------------------------|-----------|------|
| **Soll** (Default) | Belegdatum | Belegdatum | Belegdatum |
| **Ist** ab Stichtag | Zahlungseingang (`dg_voucher_payments`) anteilig | Belegdatum | Belegdatum |

Umstellung auf **Ist** nur mit gespeichertem **FA-Bescheid** (Datei + Bescheiddatum). Historie versioniert (`dg_vat_taxation_regimes`).

## UI

Einstellungen → Firma → Abschnitt „Soll- / Ist-Versteuerung“. UStVA zeigt das Regime der Periode.

## Buchung

Bei Ist: Ausgangs-USt zuerst auf Verrechnungskonto (SKR03 **1390** / SKR04 **1490**), bei Zahlung Umbuchung auf USt-Zahllast.

## Abgrenzung

- Keine automatische Retro-Korrektur bereits gemeldeter Perioden
- Kein OCR des Bescheids
- Dauerfristverlängerung getrennt
