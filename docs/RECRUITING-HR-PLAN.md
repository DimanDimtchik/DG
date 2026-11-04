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
- [x] Öffentlich: `/rekrut` Liste + Detail + Bewerbungsformular (auch im Wartungsmodus; Kopf/Navi/Fuß wie Website)
- [x] Website → Seiten: Entwurf „Karriere / Bewerbungen“ (Slug `rekrut`) analog Stempeluhr — Live bleibt `/rekrut`
- [x] Datenschutztext + Speicherdauer-Hinweis bei Bewerbung (Einwilligung Pflicht; Hinweis + Link Datenschutz)
- [x] Löschung nach Frist (`RecruitingRetentionService::runIfDue` + optional `cron.php?job=recruiting-retention`; Absage/Zurückgezogen; laufende + hired ausnehmen)
- [x] Zusage → Kontakt/Mitarbeiter (`RecruitingHireService` bei Status „Eingestellt“ → `dg_contacts` / Eigenmitarbeiter; Link in Bewerberakte)

### B — Anzeigen-Export

- [x] Stelle: Checkboxen Xing / LinkedIn + Publish-Status/Protokoll (Migration `137`, Status vorgemerkt/…, Ereignistabelle)
- [x] Xing E-Recruiting-API (nur mit Kunden-Jobvertrag / explizite Test-IDs; Settings HR → Xing; Publish-Service + OAuth 1.0a)
- [x] LinkedIn Jobs soweit freigegeben (App-Review-Haken; Job Posting API + OAuth2; Settings HR → LinkedIn; Community Management = Seiten-API, nicht Stellen-Publish)
- [x] Bewerben-URL immer Instanz-`/rekrut/...` (`JobPostingRepository::publicUrl`, im Stellenformular angezeigt)

### C — Feinschliff

- [x] E-Mail-Benachrichtigungen HR + Bewerber (Settings HR → Recruiting E-Mail; Eingang HR+Bewerber; Status Gespräch/Angebot/Zusage/Absage)
- [x] Fristen/DSGVO final mit DSB/Anwalt (Settings HR → Recruiting Datenschutz; Checkliste [`RECRUITING-DSGVO-DSB.md`](RECRUITING-DSGVO-DSB.md); Generator-Absatz Bewerbungen; Freigabe-Haken pro Instanz)
- [x] Akademie-Kurzclip; UI laut `UI-KLASSENKATALOG.md` (Szenario + `recruiting-ueberblick`; Katalog-Klassen in HR-Views; Build `bin/academy-build-recruiting-video.sh`)

## Regeln (kurz)

- Gleicher Code aller Instanzen · eigene DB
- Keine Bewerberdaten bei Xing/LinkedIn speichern (nur Anzeige + Link)
- Priorität Projekt: erst nach UI/Hetzner/Lohn-D0-Blöcken starten, außer explizit vorziehen

## Nächster Schritt

**Phase C erledigt** (Mails, DSGVO-Paket, Akademie-Clip). Offen: echte DSB-Freigabe je Instanz · Vollvideo in Akademie nach Deploy/Sync prüfen. Sonst Projekt-Prioritäten (UI/Hetzner/Lohn).
