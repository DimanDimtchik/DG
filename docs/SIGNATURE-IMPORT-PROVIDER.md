# Signatur-Import — Service-API (ES7a)

Stand: **2026-11-08** · Parent: [`CONTACT-SIGNATURE-REST-PLAN.md`](CONTACT-SIGNATURE-REST-PLAN.md)

> Vertrag (ES7a) + Adapter (ES7b). Kein Vendor-Hardcode im Produktkern.

---

## Ziel

Externe Dienste können Unterschrifts**bilder** liefern; das CRM übernimmt sie als neue Historie-Zeile:

| CRM-Feld | Inhalt |
|----------|--------|
| `source` | immer `import` |
| `source_ref` | externe Referenz-ID / Datei-Key (**kein** Secret, max. 191 Zeichen) |
| `media_id` | neues Bild in `dg_media` (PNG/WebP) |
| `user_id` / `contact_id` | Eigentümer wie bei Upload/App |

Wechsel = `UserSignatureStore::appendNew` (schließt alte `valid_to`, **kein** DELETE) — siehe ES7c.

---

## Code

| Datei | Rolle |
|-------|--------|
| `src/User/SignatureImportProvider.php` | Interface (ES7a) |
| `src/User/SignatureImportService.php` | Adapter (ES7b): Payload/Provider → media + `appendNew` |
| `UserSignatureStore::appendFromImport` | Bytes → `source=import` + `source_ref` |
| `UserSignatureStore::SOURCES` enthält `import` | seit ES1 |

Konkrete Vendor-Provider (OAuth/HTTP) bleiben Instanz-/Plugin-Sache — nicht im Kern.

---

## Interface-Vertrag (`SignatureImportProvider`)

```text
name(): string
  → Kurzname für Logs/Einstellungen (branchenneutral)

isConfigured(): bool
  → Credentials/Endpoint vorhanden (ohne Secrets zu loggen)

fetchLatestForUser(userId, contactId): ?array
  → null = nichts Neues / kein Treffer
  → sonst:
       external_ref: string   → wird source_ref
       mime: string           → image/png | image/webp
       content: string        → Rohbytes
       captured_at?: string   → optional ISO/SQL-Datetime Hinweis
       display_hint?: string  → optional UI-Text, kein HTML
```

**Nicht** Teil des Interfaces (bewusst später / CRM-intern):

- Speichern in Media + `appendNew` (Adapter-Schicht ES7b)
- OAuth/Token-Refresh-Details (Provider-spezifisch)
- Batch-Import ganzer Mandanten
- QES / Schriftform

---

## Mapping → CRM (ES7b)

1. `SignatureImportService::importFromProvider` oder `importPayload`  
2. `UserSignatureStore::appendFromImport` → Media + `appendNew` (`source=import`, `source_ref`)  
3. Re-Import mit neuer oder gleicher `external_ref` → **immer neue Zeile**, alte schließen (ES7c)

---

## Betrieb / Compliance (Kurz)

- AV/TOMs des Import-Anbieters prüfen (siehe Parent-Plan)  
- CRM speichert nur übernommene Bildmetadaten + Historie  
- Kein produktives Löschen der Historie-Zeilen  
- Keine fremde User-Signatur unter fremder Session (Import nur für den Ziel-`user_id`)

---

## Abgrenzung

| Mini | Inhalt |
|------|--------|
| **ES7a** | Vertrag + Interface-Datei |
| **ES7b** (Adapter) | `SignatureImportService` + `appendFromImport` |
| **ES7c** | Explizit: kein Auto-Löschen bei Re-Import (Verhalten bereits über `appendNew`) |
