# LinkedIn Video — Phase 2 Live-Gate (ohne Secrets)

**Stand:** 2026-11-04 · Code 2-1…2-3 ✅ · Live-Connect/Test noch offen (Credentials folgen in Tagen)

**Bezug:** [`linkedin-video-phase-2-1.md`](linkedin-video-phase-2-1.md) · [`video-publish-implementierung.md`](video-publish-implementierung.md) Phase 2

Keine Kundenfirma und keine Personen im Kern hardcoden. App und Company Page gehören zur **jeweiligen** Tenant-Instanz (juristische Person + Page-Admin).

---

## Status

| Teil | Stand |
|------|--------|
| CRM Settings / OAuth / Upload / Posts / Kommentare / Insights | Code fertig, deployed |
| LinkedIn Developer App + Community Management (Dev Tier) | manuell, wartet auf Daten |
| Connect + Smoke auf Dev | danach |
| Standard Tier + Screencast | nach erstem erfolgreichen Smoke |

---

## A — Redirect-URIs (in die LinkedIn-App eintragen)

Muster: `{publicBaseUrl}/oauth/linkedin/callback`  
Im CRM: Einstellungen → Kommunikation → **LinkedIn Publish** (Readonly-Feld „Redirect-URI“).

Ops-Hosts (Infrastruktur, nicht Produkt-Default) — je Instanz nur die URI der Instanz, auf der getestet/verbunden wird:

| Instanz-Rolle | Redirect-URI |
|---------------|--------------|
| Dev | `https://ganz-om.de/oauth/linkedin/callback` |
| Firma (eigene Nutzung) | `https://dg.ganz-om.de/oauth/linkedin/callback` |
| Kundeninstanz A | `https://ganz-soft.de/oauth/linkedin/callback` |
| Kundeninstanz B | `https://kontur-cosmetics.de/oauth/linkedin/callback` |
| Kundeninstanz (anderer Account) | `https://office.iqstrom.com/oauth/linkedin/callback` |

**Empfehlung:** zuerst **nur Dev** in der App hinterlegen und dort Connect + Smoke. Weitere URIs ergänzen, wenn dieselbe App (selten) oder jeweils die Tenant-App für die Produktiv-Instanz.

Pro Tenant typisch: **eigene** LinkedIn-App unter dessen Legal Entity → eine Redirect-URI = deren CRM-`publicBaseUrl`.

---

## B — Was die LinkedIn-App braucht (Checkliste zum Abhaken)

- [ ] App unter **juristischer Person** des Seitenbetreibers
- [ ] Business-E-Mail, Legal Name, Adresse, Website
- [ ] Privacy-Policy-URL der Instanz (öffentliche Seite, z. B. `…/datenschutz`)
- [ ] Produkt **Community Management** → Development Tier
- [ ] Auth-Redirect-URI(s) wie Tabelle A
- [ ] Products/Scopes freigeben, die das CRM anfordert (siehe Settings-UI):  
  `openid` `profile` `w_organization_social` `r_organization_social`  
  `w_organization_social_feed` `r_organization_social_feed`
- [ ] Page-Admin-Member notieren (Person, die „Mit LinkedIn verbinden“ klickt)
- [ ] Organization ID (nur Ziffern) → CRM-Feld; URN wird `urn:li:organization:{id}`
- [ ] Client ID + Client Secret → CRM speichern, Schalter an, Review-Stufe „Development Tier“
- [ ] Connect → Status *verbunden*
- [ ] Limits Dev-Tier kennen (~500 Calls/App, ~100/Member / 24h)

---

## C — Smoke nach Connect (Dev)

Kurzes MP4 (Feed-tauglich: MP4, Größe/Dauer laut LinkedIn Videos API). Keine echten personenbezogenen Bewerberdaten.

1. [ ] Modul **Videos**: Asset hochladen  
2. [ ] LinkedIn veröffentlichen (sofort oder `scheduled_at` + Cron `video-publish`)  
3. [ ] Post erscheint auf der Company Page  
4. [ ] Commentary im CRM ändern → auf LinkedIn sichtbar  
5. [ ] Insights-Abschnitt lädt (Views/Summary soweit Scope erlaubt)  
6. [ ] Kommentar als Org antworten (Testthread)  
7. [ ] Optional: Post aus CRM löschen (nur Testpost)

Fehler: Job `failed` + lesbare Meldung; keine Tokens in Logs.

---

## D — Screencast-Skript (Standard Tier, später)

Laut [Community Management App Review](https://learn.microsoft.com/en-us/linkedin/marketing/community-management-app-review?view=li-lms-2026-06). Ablehnung → **neue App** (kein Re-Apply).

Aufnahme-Reihenfolge (CRM-UI, Demo-Tenant, keine echten Kundendaten):

1. Einstellungen → LinkedIn Publish: Credentials gesetzt, Redirect-URI zeigen  
2. „Mit LinkedIn verbinden“ → OAuth → zurück, Status verbunden  
3. Videos: Datei wählen → auf Company Page posten → Feed kurz zeigen  
4. Kommentare listen / als Organisation antworten  
5. Insights/Summary kurz zeigen  
6. Welche Datenfelder gespeichert werden (Connection, Job-Status, externe URN — keine Passwörter)

Notizfeld im CRM (`app_review_note`) für Antragsdatum / Screencast-Link nutzen.

---

## E — Bewusst später / nicht Blocker für Phase 3

- Programmatic Refresh (~1 Jahr) — Partner-Freischaltung; sonst Re-Connect vor Access-Ablauf (~60 Tage)
- `w_member_social` (persönliche Posts) — optional
- Standard Tier — nach stabilem Dev-Smoke
- YouTube Analytics / weiteres Engagement — eigene Phasen

---

## Wenn Credentials da sind

1. Checkliste B auf Dev abarbeiten  
2. Smoke C  
3. Haken in [`linkedin-video-phase-2-1.md`](linkedin-video-phase-2-1.md) setzen  
4. Parallel oder danach Phase 3 (Instagram) fortsetzen
