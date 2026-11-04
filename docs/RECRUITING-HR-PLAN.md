# Recruiting in HR — Plan (knapp)

Stand: 2026-11-03 · **Kern = CRM** · Xing/LinkedIn nur Anzeigen-Reichweite · Bewerbung immer auf eigener Seite (`/rekrut` o. Ä.)

## Zielbild

Stelle im CRM → optional Publish Xing/LinkedIn (Link = unsere URL) → Bewerber registriert/bewirbt sich bei uns → Akte im CRM → Absage: Auto-Löschung nach Frist · Zusage: Übernahme Mitarbeiterstamm.

## Nicht im Scope (Phase 0–2)

- LinkedIn/Xing-Personensuche, InMail/Chat-Spiegel
- Social-Posting außerhalb Stellenanzeigen
- Aggregator-SaaS

## Phasen

### A — CRM + eigene Bewerbungsseite (ohne Plattform-API)

- [x] Menü HR: Stellen, Bewerber (`hr-stellen`, `hr-bewerber` — Platzhalter-Seiten)
- [x] Daten: Migration `136_recruiting.sql` · `dg_job_postings` / `dg_applicants` / `dg_job_applications` (+ Events/Files) · CRM-CRUD
- [ ] Öffentlich: `/rekrut` Liste + Detail + Registrierung/Bewerbung (pro Instanz)
- [ ] Datenschutztext + Speicherdauer-Hinweis bei Bewerbung
- [ ] Cron: Löschung nach Frist (Absage/Ende Verfahren; laufende Verfahren ausnehmen)
- [ ] Zusage → Kontakt/Mitarbeiter (bestehendes HR-Modell nutzen)

### B — Anzeigen-Export

- [ ] Stelle: Checkboxen Xing / LinkedIn + Publish-Status/Protokoll
- [ ] Xing E-Recruiting-API (nur mit Kunden-Jobvertrag)
- [ ] LinkedIn Jobs / Community soweit freigegeben (App-Review)
- [ ] Bewerben-URL immer Instanz-`/rekrut/...`

### C — Feinschliff

- [ ] E-Mail-Benachrichtigungen HR + Bewerber
- [ ] Fristen/DSGVO final mit DSB/Anwalt
- [ ] Akademie-Kurzclip; UI laut `UI-KLASSENKATALOG.md`

## Regeln (kurz)

- Gleicher Code aller Instanzen · eigene DB
- Keine Bewerberdaten bei Xing/LinkedIn speichern (nur Anzeige + Link)
- Priorität Projekt: erst nach UI/Hetzner/Lohn-D0-Blöcken starten, außer explizit vorziehen

## Nächster Schritt

Nur **Phase A** spezifizieren/umsetzen, wenn Priorität freigegeben.
