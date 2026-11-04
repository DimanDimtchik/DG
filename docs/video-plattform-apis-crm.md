# Video-Plattform-APIs für Self-hosted Multi-Tenant-CRM

Stand: Recherche Nov 2026 (offizielle Docs 2025–2026). Fokus: Server-Upload, One-Click-Publish, Post-Management.

**Kurz-Urteile**

| Plattform | Verdict |
|-----------|---------|
| YouTube Data API | **gut für CRM-MVP** |
| LinkedIn Community Management | **möglich aber schwer** |
| TikTok Content Posting | **möglich aber schwer** (Produkt-CRM); **kaum praktikabel** (nur internes Tool) |
| Instagram / Meta Graph | **möglich aber schwer** |

---

## 1. YouTube (Data API v3)

### Server-Upload?
**Ja.** Offizieller Server-Flow: OAuth 2.0 Web-Server-App → `videos.insert` mit Resumable Upload (`uploadType=resumable`). Kein Browser-only.

- Docs: [videos.insert](https://developers.google.com/youtube/v3/docs/videos/insert), [Resumable Uploads](https://developers.google.com/youtube/v3/guides/using_resumable_upload_protocol), [Server-side OAuth](https://developers.google.com/youtube/v3/guides/auth/server-side-web-apps)

### Account-Typen
- Jeder YouTube-Kanal (persönlich oder Brand-Channel), den der User via Google OAuth freigibt.
- Kein „Business-only“-Zwang für Upload.
- **YouTube Content ID / Partner API** (`youtubepartner`): nur für YouTube-Partner mit Content-ID-Vertrag — für normales CRM-Publish **irrelevant**, weglassen.

### App Review / Hürden (Multi-Tenant)
| Stufe | Aufwand |
|-------|---------|
| GCP-Projekt + YouTube Data API aktivieren | niedrig |
| OAuth Consent Screen „Testing“ | max. ~100 Testuser; Refresh-Tokens oft 7 Tage |
| Production + OAuth-Verification (sensitive/restricted Scopes: `youtube.upload`, `youtube.force-ssl`, …) | mittel — Screencast, Privacy Policy, Use-Case |
| Quota-Audit für >100 Uploads/Tag | mittel–hoch — Compliance-Audit nötig |

Default-Quota: **100 `videos.insert`/Tag** (eigenes Bucket) + 10.000 Units sonstige Calls. Mehr nur nach Audit.  
→ Für wenige Tenants/MVP ok; bei vielen Tenants früh Quota beantragen.

### Nach Publish verwaltbar?
| Aktion | API? |
|--------|------|
| Caption/Titel/Beschreibung/Tags editieren | Ja — `videos.update` |
| Löschen | Ja — `videos.delete` |
| Planen | Ja — `status.publishAt` + `privacyStatus=private` (nur vor Erstveröffentlichung) |
| Kommentare lesen/moderieren | Ja — Comments / `setModerationStatus` |
| Insights/Analytics | Ja — separate [YouTube Analytics API](https://developers.google.com/youtube/analytics) (`yt-analytics.readonly`) |
| Thumbnail setzen | Ja — `thumbnails.set` |

### Harte Limits (CRM-relevant)
- Dateigröße: bis **256 GB**; MIME `video/*` oder `application/octet-stream`
- Upload-Quota: **100 Inserts/Tag** Default
- Resumable Sessions laufen ab → Chunking/Retry im CRM einbauen
- Kein festes Dauer-/Format-Hardcap in der Insert-Doc; Encoding-Empfehlungen in YouTube-Help gelten praktisch

### OAuth
- **Pro Tenant/Kanal:** 3-legged OAuth, Offline-Access, Refresh-Token serverseitig speichern.
- Access-Token kurzlebig; Refresh ohne User-Interaktion, bis Widerruf / Policy-Ablauf.
- Production-Verification nötig, bevor viele externe Tenants verbinden.

### Verdict: **gut für CRM-MVP**
Klare Server-API, volle Post-Lifecycle-Kontrolle, planbar. Content ID weglassen. Quota und OAuth-Verification früh einplanen.

---

## 2. LinkedIn (Community Management / Videos / Posts)

### Server-Upload?
**Ja.** Aktuell: **Videos API** (Multipart) → Video-URN → **Posts API** (ersetzt legacy `ugcPosts`).

1. `POST /rest/videos?action=initializeUpload`
2. Chunks hochladen (~4 MB)
3. Finalize → `urn:li:video:…`
4. `POST /rest/posts` mit `lifecycleState: PUBLISHED`

- Docs: [Videos API](https://learn.microsoft.com/en-us/linkedin/marketing/community-management/shares/videos-api?view=li-lms-2026-10), [Posts API](https://learn.microsoft.com/en-us/linkedin/marketing/community-management/shares/posts-api?view=li-lms-2026-10)
- Header: `Linkedin-Version: YYYYMM`, `X-Restli-Protocol-Version: 2.0.0` — Versionen sunsetting (z. B. 202511 → Nov 2026)

### Account-Typen
| Ziel | Scope / Rolle |
|------|----------------|
| Persönliches Profil | `w_member_social` / `r_member_social` — Member OAuth |
| Company Page | `w_organization_social` / `r_organization_social` — Member muss Page-Admin-Rolle haben |
| Owner URN | `urn:li:person:…` oder `urn:li:organization:…` |

Beide Pfade in Community Management vorgesehen (Page Management + Executive/Employee Advocacy).

### App Review / Hürden (Multi-Tenant)
**Hoch, aber für kommerzielles CRM vorgesehen.**

- Community Management nur für **eingetragene juristische Personen**, kommerzieller Use-Case.
- Business-E-Mail, Legal Name, Adresse, Website, Privacy Policy; Page-Super-Admin muss App verifizieren.
- **Development Tier** → danach **Standard Tier** mit Screencast (OAuth, Posten, Kommentare anzeigen, Datenfelder).
- Ablehnung → neue App nötig (kein Re-Apply auf derselben App).
- Doc: [Community Management App Review](https://learn.microsoft.com/en-us/linkedin/marketing/community-management-app-review?view=li-lms-2026-06)

Dev-Tier-Limits (Overview): ca. **500 Calls/App** und **100/Member** pro 24h — für Produktion Standard Tier nötig.

### Nach Publish verwaltbar?
| Aktion | API? |
|--------|------|
| Caption (`commentary`) editieren | Ja — Posts PARTIAL_UPDATE |
| Löschen | Ja — `DELETE /rest/posts/{urn}` |
| Planen | **Nein nativ** — CRM speichert Zeit und publisht zum Termin |
| Kommentare CRUD | Ja — [Comments / Social Actions](https://learn.microsoft.com/en-us/linkedin/marketing/community-management/shares/comments-api?view=li-lms-2026-08) |
| Insights | Ja — Page/Share/Video Analytics (Page Analytics Use-Case) |
| Comments Section ein/aus | Social Metadata API |

### Harte Limits
- Video: **MP4**, **75 KB–500 MB** (Feed-Specs); Länge **3 s–30 Min**
- `fileSizeBytes` in Initialize bis **5 GB** dokumentiert — Feed-Serving-Specs enger halten (500 MB)
- Multipart: typ. ~4 MB Parts
- Rate Limits nicht global publiziert; App-Analytics; Dev-Tier eng

### OAuth
- 3-legged OAuth **pro Member** (Tenant verbindet Page-Admin bzw. Profil).
- Access-Token: **~60 Tage**.
- Programmatic Refresh-Tokens (**~1 Jahr**): nur für freigeschaltete Marketing-Partner; danach Re-Auth.
- Ohne Refresh: vor Ablauf erneut verbinden (Consent oft still, wenn Session aktiv).

### Verdict: **möglich aber schwer**
Technisch solide Server-Pipeline und gutes Post-Management. Gatekeeper ist App Review (Legal Org + Standard Tier). Für B2B-Company-Pages (plus optional persönlich) der richtige zweite Kanal nach YouTube — Review-Aufwand ernst nehmen.

---

## 3. TikTok (Content Posting API + Login Kit)

### Server-Upload?
**Ja.** Content Posting API für Desktop/Cloud/Web.

| Modus | Endpoint-Idee | Ergebnis |
|-------|---------------|----------|
| **Direct Post** | `/v2/post/publish/video/init/` + Scope `video.publish` | Sofort posten |
| **Inbox Upload** | `/v2/post/publish/inbox/video/init/` + Scope `video.upload` | Draft in TikTok-Inbox; Creator beendet in App |

Transfer: `FILE_UPLOAD` (PUT Chunks) oder `PULL_FROM_URL` (Domain/URL-Prefix in TT4D verifiziert).

- Docs: [Content Posting API](https://developers.tiktok.com/products/content-posting-api), [Direct Post Get Started](https://developers.tiktok.com/doc/content-posting-api-get-started), [Media Transfer](https://developers.tiktok.com/doc/content-posting-api-media-transfer-guide)

### Account-Typen
- Creator-Accounts (Login Kit OAuth); kein separates „Business Page“-Modell wie Meta/LinkedIn.
- Public Direct Post erst nach **Client-Audit**.

### App Review / Hürden (Multi-Tenant) — kritisch
1. Scope-Freigabe `video.publish` / `video.upload`.
2. **Audit** für öffentliche Direct Posts; ohne Audit:
   - nur `SELF_ONLY` / private Accounts
   - max. **5 User / 24h**
3. [Content Sharing Guidelines](https://developers.tiktok.com/doc/content-sharing-guidelines) (Stand Aug 2026):
   - **Nicht akzeptiert:** „utility tool to help upload contents to the account(s) you or your team manages“
   - App muss für **breite Audience** gedacht sein, nicht nur interne Gruppen
   - Strenge UX-Pflichten: Creator-Info jedes Mal laden, Privacy manuell wählen (kein Default), Commercial Disclosure, Consent-Texte, Preview, kein Watermark
4. Caps nach Audit: Creator-Cap laut Audit-Schätzung; ~**15 Posts/Creator/Tag** typisch (geteilt über alle Clients)

→ Reines internes Firmen-Tool: **Audit-Risiko hoch**. Multi-Tenant-CRM als öffentliches Creator-/Marketing-Produkt: möglich, aber UX- und Policy-Aufwand groß.

### Nach Publish verwaltbar?
| Aktion | API? |
|--------|------|
| Caption nachträglich editieren | praktisch **nein** über Posting-API |
| Löschen | nicht Kern der Posting-API |
| Planen | **nein** — CRM hält zurück und postet live |
| Status | Ja — `publish/status/fetch` / Webhooks |
| Insights / Kommentare | andere TikTok-APIs / eingeschränkt — Posting ≠ volles Community-Management |

Direct Post = Publish-fokussiert, kein LinkedIn-/YouTube-ähnliches Post-CMS.

### Harte Limits
- Format: MP4 (empfohlen), WebM, MOV; Codecs H.264/H.265/VP8/VP9
- Größe: max. **4 GB**
- Dauer: API max. **10 Min**; Account oft 3 / 5 / 10 Min → immer `max_video_post_duration_sec` aus Creator-Info prüfen
- Auflösung: 360–4096 px; 23–60 FPS
- Caption: max. **2200** UTF-16
- Rate: **6 Requests/Min** pro User-Token (Init)
- Inbox: max. **5 pending shares / 24h**

### OAuth (Login Kit)
- Pro Creator/Tenant verbinden.
- Access-Token: **24 h**; Refresh-Token: **365 Tage**; neuer Refresh kann rotieren → immer speichern.
- Doc: [OAuth Token Management](https://developers.tiktok.com/doc/oauth-user-access-token-management)

### Verdict: **möglich aber schwer** (Multi-Tenant-Produkt) / **kaum praktikabel** (nur DG-intern)
Technik ok; Policy/UX/Audit machen „One-Click aus dem CRM“ teuer. Nicht als Phase-1-Kanal. Für Tenants nur, wenn CRM klar als externes Creator-Tool positioniert und UX-Guidelines 1:1 umgesetzt werden. Inbox-Upload als Fallback = kein echter One-Click.

---

## 4. Instagram / Meta (Graph API Content Publishing / Reels)

### Server-Upload?
**Ja**, aber oft **indirekt**: öffentlich erreichbare `video_url` **oder** resumable Upload über Meta (`rupload.facebook.com`, vor allem mit Facebook Login for Business).

Flow:
1. `POST /{ig-user-id}/media` (`media_type=REELS`, `video_url` / resumable)
2. Container pollen (`status_code` → `FINISHED`)
3. `POST /{ig-user-id}/media_publish`

- Docs: [Content Publishing](https://developers.facebook.com/docs/instagram-platform/instagram-graph-api/content-publishing/), [IG User Media](https://developers.facebook.com/docs/instagram-platform/instagram-graph-api/reference/ig-user/media/)

### Account-Typen
- **Nur Instagram Professional** (Business oder Creator). Persönliche Consumer-Accounts: **kein** Publishing.
- Zwei Auth-Pfade:
  - **Instagram API with Instagram Login** — einfacher; eingeschränkt (z. B. Delete oft nicht)
  - **Instagram API with Facebook Login** — Page verknüpft; mehr Features (Delete, Product Tagging, …)

### App Review / Hürden (Multi-Tenant)
| Für eigene Accounts (Standard Access / Rollen auf App) | Niedrig–mittel |
| **Advanced Access** für fremde Tenant-Accounts | **Hoch**: App Review + oft **Business Verification**, Screencasts, Permissions `instagram_content_publish` / `instagram_business_content_publish` |
| Meta Business Portfolio / Policy | laufende Compliance |

Doc: [App Review Instagram](https://developers.facebook.com/docs/instagram-platform/app-review/)

### Nach Publish verwaltbar?
| Aktion | API? |
|--------|------|
| Caption nach Publish editieren | **Nein** (typisch) |
| Löschen | Facebook-Login-Pfad: ja; Instagram-Login: oft **nicht** |
| Planen | **kein** natives Publish-at — CRM deferred publish |
| Kommentare | Ja — lesen, antworten, verstecken, löschen |
| Insights | Ja — Media/Account Insights |
| Stories | eingeschränkt publishbar (ohne Caption etc.) |

### Harte Limits (Reels)
- Container: **MP4/MOV**; H.264/HEVC; AAC; 23–60 FPS
- Dauer: **3 s–15 Min**; Datei max. **~300 MB**; max. Breite 1920; 9:16 empfohlen
- Container **24 h** gültig; max. **~400 Containers / 24 h**
- Publish: oft **~100 API-Posts / 24 h** pro Account (Carousel = 1); Quota via `content_publishing_limit` prüfen
- Caption: bis ~2200 Zeichen

### OAuth
- Pro Tenant: Professional Account verbinden.
- Instagram Login: Short-lived → Long-lived (**~60 Tage**), Refresh solange Token ≥24 h alt und unexpired.
- Facebook Login: User-Token → Page-Token; Long-lived Page-Tokens pflegen/refreshen.
- Tokens serverseitig, Tenant-isoliert speichern.

### Verdict: **möglich aber schwer**
Technisch machbar für Professional Accounts; Multi-Tenant braucht Advanced Access + Verification. Post-Management schwächer als YouTube/LinkedIn (kein Caption-Edit, Scheduling nur CRM-seitig). Für DG persönlich nachrangig; für andere Tenants Phase 3+.

---

## Vergleich (CRM-Design)

| Kriterium | YouTube | LinkedIn | TikTok | Instagram |
|-----------|---------|----------|--------|-----------|
| Server-Upload | ✅ | ✅ | ✅ | ✅ (URL/resumable) |
| One-Click public | ✅ | ✅ nach Review | ✅ nur nach Audit + UX | ✅ nach Review |
| Caption edit | ✅ | ✅ | ❌ | ❌ |
| Delete | ✅ | ✅ | eingeschränkt | Login-Pfad abhängig |
| Native Schedule | ✅ (`publishAt`) | ❌ | ❌ | ❌ |
| Insights | ✅ Analytics API | ✅ | eingeschränkt | ✅ |
| Comments | ✅ | ✅ | eingeschränkt | ✅ |
| Multi-Tenant-Hürde | OAuth-Verify + Quota | Legal Org + CM Review | Audit + „kein Utility-Tool“ | Advanced Access + BV |
| Typ. Datei-Limit | sehr hoch | ~500 MB MP4 | 4 GB | ~300 MB Reels |

**Architektur-Hinweise für DG CRM**
- Pro Tenant/Plattform: OAuth-Connect, Token-Vault, Refresh-Jobs.
- Video im CRM speichern; Publish-Worker asynchron (Chunk/Resume, Status-Polling).
- Scheduling: nur YouTube nativ; sonst CRM-Cron → API zum Termin.
- Transcode-Pipeline: Zielprofile MP4 H.264 (LinkedIn/TikTok/IG Reels).
- Feature-Flags pro Plattform/Tenant (Review-Status, Audit-Status).

---

## Empfohlene Phasen — Multi-Tenant CRM

### Phase 1 — MVP: YouTube
1. GCP + YouTube Data API, Resumable Upload, Offline-OAuth.
2. CRM: Video ablegen → One-Click Upload → Titel/Beschreibung/Privacy → Liste + Edit/Delete.
3. Optional: `publishAt` für Planung; später Analytics-Scope.
4. Parallel: OAuth Production Verification + Quota-Monitoring anstoßen.

**Warum zuerst:** beste API-Passung, wenig Partner-Politik, volles Post-Management.

### Phase 2 — LinkedIn (Company Page)
1. LinkedIn Developer App als **juristische Person des Tenants**; Community Management Development → Integration → Standard Tier (Screencast).  
   **CRM-Scaffold 2-1:** Settings-Tab *LinkedIn Publish* + OAuth-Connect — [`linkedin-video-phase-2-1.md`](linkedin-video-phase-2-1.md).  
   **Live-Gate (Redirects, Smoke, Screencast):** [`linkedin-video-phase-2-live.md`](linkedin-video-phase-2-live.md) — App-Anlage manuell **pro Tenant**.
2. Primär **Company Page** (`w_organization_social`); optional Member-Posts.  
   **Code 2-2:** Multipart-Upload + Posts create/edit/delete + CRM-`scheduled_at` im Modul Videos.
3. Videos API + Posts API; Scheduling im CRM; Comments/Insights — **Code 2-3**.

**Warum zweitens:** Review planbar; gut für B2B-Company-Pages; Credentials nur in Instanz-Settings.

### Phase 3 — weitere CRM-Tenants: Instagram
1. Meta App, Business Verification, Advanced Access für `content_publish`.
2. Nur Professional Accounts; Reels-Pipeline (300 MB, Transcode).
3. Erwartung managen: kein Caption-Edit nach Publish; Delete je nach Login-Pfad.

### Phase 4 — optional / vorsichtig: TikTok
1. Nur wenn CRM klar als **öffentliches Multi-Tenant-Creator-/Marketing-Produkt** positioniert ist (nicht „internes Upload-Hilfstool“).
2. UX-Guidelines 1:1; dann Audit für Direct Post.
3. Bis Audit: höchstens Inbox-Upload als „Draft nach TikTok“ — kein echter One-Click.
4. Für rein internes Einzelpersonen-Nutzen: eher **skip** oder manuell in der TikTok-App.

### Nicht tun
- Content ID / YouTube Partner API ohne Partnervertrag.
- TikTok/Instagram vor YouTube+LinkedIn bauen.
- Browser-only / Scraping / inoffizielle APIs.
- Eine gemeinsame „Social Quota“ annehmen — Limits sind **pro Plattform + oft pro Account**.

---

## Quellen (offiziell)

- YouTube: [videos.insert](https://developers.google.com/youtube/v3/docs/videos/insert), [Quota](https://developers.google.com/youtube/v3/determine_quota_cost), [Quota & Compliance Audits](https://developers.google.com/youtube/v3/guides/quota_and_compliance_audits), [OAuth Web Server](https://developers.google.com/youtube/v3/guides/auth/server-side-web-apps)
- LinkedIn: [Videos API](https://learn.microsoft.com/en-us/linkedin/marketing/community-management/shares/videos-api?view=li-lms-2026-10), [Posts API](https://learn.microsoft.com/en-us/linkedin/marketing/community-management/shares/posts-api?view=li-lms-2026-10), [App Review](https://learn.microsoft.com/en-us/linkedin/marketing/community-management-app-review?view=li-lms-2026-06), [Refresh Tokens](https://learn.microsoft.com/en-us/linkedin/shared/authentication/programmatic-refresh-tokens), [Comments](https://learn.microsoft.com/en-us/linkedin/marketing/community-management/shares/comments-api?view=li-lms-2026-08)
- TikTok: [Content Posting](https://developers.tiktok.com/products/content-posting-api), [Guidelines](https://developers.tiktok.com/doc/content-sharing-guidelines), [Direct Post](https://developers.tiktok.com/doc/content-posting-api-reference-direct-post), [Media Transfer](https://developers.tiktok.com/doc/content-posting-api-media-transfer-guide), [OAuth](https://developers.tiktok.com/doc/oauth-user-access-token-management)
- Instagram/Meta: [Content Publishing](https://developers.facebook.com/docs/instagram-platform/instagram-graph-api/content-publishing/), [IG Media](https://developers.facebook.com/docs/instagram-platform/instagram-graph-api/reference/ig-user/media/), [App Review](https://developers.facebook.com/docs/instagram-platform/app-review/)
