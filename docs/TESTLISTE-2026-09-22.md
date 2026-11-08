# Testliste CRM — Sep 2026

> **Interaktiv:** Cursor-Canvas *Testliste CRM — Sep 2026*  
> Datei: `.cursor/projects/.../canvases/testliste-sep-2026.canvas.tsx` (neben dem Chat öffnen)  
> Abhak-Stand speichert sich in der Canvas-Sidecar; Agent kann ihn lesen.

**Instanz:** https://ganz-soft.de · nach Deploy **Shift+Strg+R**  
**Quellen:** offene Punkte aus `TODOS.md` + Zeiterfassung Z2–Z6 + Spot Deploy

---

## Bereits erledigt (Sep 2026)

- [x] Lager / Einkauf
- [x] Akademie
- [x] Terminkalender / Online-Buchung
- [x] Rechtstexte
- [x] Wartungsseite + Kontakt-Link

---

## 0 · Smoke nach Deploy

- [x] Login — Admin-User Dev ✅ PA2g
- [x] Menü Zeiterfassung sichtbar — ✅ PA2g 2026-11-08
- [x] Keine 500er auf Lohn-Export / Urlaub / Rückstellung / Schichten — ✅ PA2g Service-Smoke
- [x] Lohn-Export-Protokoll speichert Download (Migration 094) — ✅ PA2g `export_id` CSV/DATEV/Lex

## 1 · Zeiterfassung Phase 2

- [x] Ein-/Ausstempeln + Tagesübersicht — ✅ PA2g 2026-11-08 Dev
- [x] Team heute — ✅ PA2g
- [x] Monatsblatt Soll/Ist/Pause/Diff/ÜStd — ✅ PA2g
- [x] Zeitkonto Saldo + Lots — ✅ PA2g API (`listOpenLots`)
- [x] Korrektur — ✅ PA2g

## 2 · Phase 3 Schichten

- [x] Vorlagen — ✅ PA2g (3 Vorlagen)
- [x] Wochenraster MA × Tag — ✅ PA2g Zuordnung `upsert` (Einzel-Tag; UI-Raster analog)
- [x] Soll im Monatsblatt — ✅ PA2g `schedule_source=shift`

## 3 · Phase 4 Urlaub / Krankheit

- [x] Anspruch — ✅ PA2g
- [x] Antrag + Freigabe — ✅ PA2g
- [x] Krankheit + Attest-Ref — ✅ PA2g
- [x] Genehmigt → Soll = 0 — ✅ PA2g

## 4 · Phase 5 Rückstellungen

- [x] Preview + Settings + CSV — ✅ PA2g 2026-11-08 Dev
- [x] Bestätigung → Manual Ledger — ✅ PA2g (Smoke-Jahr 2096, Konten 4120/0970; Default 6140 fehlt im Dev-Rahmen)
- [x] Jahresabschluss-Checkliste — ✅ PA2e 2026-11-08 Dev (FiscalCloseService + close/reopen/Sperre)

## 5 · Phase 6 Lohn-Export (neu)

- [x] Vorschau Monat — ✅ PA2g
- [x] CSV + Protokoll — ✅ PA2g
- [x] DATEV (Berater/Mandant) — ✅ PA2f FiBu + ✅ PA2g Lohn-DATEV mit Temp-Nummern
- [x] Ohne Settings: Blockade — ✅ PA2g
- [x] Lexoffice — ✅ PA2g
- [ ] payroll_slip Upload in Kontaktakte
- [ ] Rechte ohne Team
- [ ] Kontakte: Excel/CSV-Import (ShiftBase/Mitarbeiter), optional Kalender-Link

## 6 · Offen von früher

- [x] Belegkette Angebot→AB→…→Schluss — ✅ PA2a 2026-11-08 Dev (inkl. LS; Schluss via Abschlag)
- [x] Dokumentstatus / Filter — ✅ PA2b 2026-11-08
- [x] Freitext + Klauseln Druck — ✅ PA2b
- [x] Skonto + Mahnung — ✅ PA2b (Candidates; Versand braucht SMTP)
- [x] Teilzahlungen / OPOS — ✅ PA2b
- [x] Bank Geisterumsätze (064) — ✅ PA2c 2026-11-08 Dev
- [x] Kichel — ✅ PA2h 2026-11-08 Dev
- [ ] Belegdarstellung / AB-Druck Stichprobe
- [x] §14-UStG Druckstichprobe Ausgangsbelege (Docarten) — ✅ PA1a 2026-11-08 · gemischte Sätze → PA1b
  → [`USTG-14-DRUCKSTICHPROBE.md`](USTG-14-DRUCKSTICHPROBE.md)

## 7 · Spot

- [ ] dg.ganz-om.de Login + Zeiterfassung
- [ ] kontur-cosmetics.de Login + eine Seite

## 8 · Kommunikation / Postfächer (P1–P6)

Vollständige Smoke-Liste: [`KOMMUNIKATION-POSTFAECHER-TODO.md`](KOMMUNIKATION-POSTFAECHER-TODO.md) §P6.

- [x] Master: Liste → Ordner → Nachricht → Anhang — ✅ PA2i 2026-11-08 Dev (Liste/Ordner/lokale Nachricht; Live-IMAP-Anhang offen bis Credentials)
- [x] Shared Lesen vs. Senden — ✅ PA2i
- [x] Compose + CRM-Link + Suche — ✅ PA2i (Compose-Validierung + Kontakt/Beleg + `q=`)
- [ ] Deploy Master → `sync-crm-from-master.sh` → Stichprobe ganz-soft / kontur

---

Ältere Listen: [`TESTLISTE-2026-08-21.md`](TESTLISTE-2026-08-21.md) (Statistik, Shop, Buchhaltung A–C) — bei Bedarf separat.
