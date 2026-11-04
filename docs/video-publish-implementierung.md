# Video-Publish — Implementierungsplan DG CRM (Multi-Tenant)

**Für:** Dietrich / IQ Strom + spätere CRM-Tenants · **Stand:** Nov. 2026  
**Bezug:** [`video-plattform-apis-crm.md`](video-plattform-apis-crm.md) (API-Recherche; nicht duplizieren)  
**Prefs:** all-inkl jetzt · VPS später · eigene CRM-Lösung · kein fremder Social-Gateway  
**Reihenfolge (Pflicht):** Phase 1 YouTube → 2 LinkedIn → 3 Instagram → 4 TikTok  
**Kein Code / kein PR** — Implementierungsplan für spätere Agents.

---

## Hosting-Legende

| Symbol | Bedeutung |
|--------|-----------|
| **W** | Machbar auf aktuellem **all-inkl-Webhosting** (PHP Request + Cron) |
| **V** | Braucht **VPS / Queue-Worker** (lange Uploads, Chunk-Resume, robustes Retry) |
| **W→V** | MVP auf W mit Limits; Produktion/Skalierung auf V |

---

## 0. Shared Foundation (einmal, vor Phase 1)

**Ziel:** Minimales Publish-Gerüst — keine Plattform-Logik außer Stubs.

| Baustein | Inhalt | Host |
|----------|--------|------|
| OAuth-Store | Tabelle pro Tenant+Plattform: tokens (encrypted), scopes, expires, refresh, status | **W** |
| Media-Blob | Video-Datei im CRM speichern (Pfad/Storage), Meta (mime, size, duration?, hash) | **W** (kleine Dateien); **V** ab ~100–300 MB zuverlässig |
| Job-Queue | `publish_jobs`: pending→uploading→published/failed; Retry; Status-Polling | **W** Cron-Polling; **V** echter Worker |
| Publish-UI | Button „Veröffentlichen“ + Plattform-Checkboxen + Titel/Caption/Privacy-Felder | **W** |
| Feature-Flags | Pro Tenant+Plattform: `connected` / `review_ok` / `enabled` | **W** |

### Datenmodell (Minimum — shared)

| Tabelle / Entity | Kernfelder |
|------------------|------------|
| `video_assets` | tenant_id, path/blob_ref, mime, bytes, created_by, created_at |
| `oauth_connections` | tenant_id, platform, account_urn/id, access_enc, refresh_enc, expires_at, scopes, status |
| `publish_jobs` | tenant_id, asset_id, platform, state, scheduled_at?, attempts, last_error, external_id/urn |
| `publish_posts` | job_id, platform, external_id, title/caption snapshot, privacy, published_at, editable_flags |

### Do NOT yet (Foundation)

- [x] Transcode-Pipeline (nur speichern; Format-Checks später) — bewusst ausgelassen
- [x] Analytics/Comments-Module — bewusst ausgelassen
- [x] Multi-Plattform-Batch in einem Job — bewusst ausgelassen
- [x] Fremde SaaS (Buffer, Hootsuite, …) — bewusst ausgelassen

### Acceptance Foundation

- [x] Tenant A Tokens unsichtbar für Tenant B — Multi-Tenant = getrennte Instanz-DB + Crypto (`VideoPublishCrypto`)
- [x] Asset hochladen → in Liste sichtbar — Modul **Videos** + `dg_video_assets` / `storage/video-publish/`
- [x] Job anlegen → Status-Cron ändert State stub (ohne echte API) — `dg_publish_jobs` + `cron.php?job=video-publish` / `VideoPublishQueueService::runIfDue`
- [x] Publish-Button disabled wenn Plattform nicht connected — Formular nur bei verbundenem YouTube

**Umsetzung:** Migration `138_video_publish.sql`, Klassen unter `src/VideoPublish/`, Selftest `bin/video-publish-selftest.php`.

---

## Phase 1 — YouTube

**Ziel:** One-Click Upload + Edit/Delete (+ optional Schedule) für eigenen Kanal; Multi-Tenant-fähig nach OAuth-Verify.

### Prerequisites

| Item | Pflicht |
|------|---------|
| GCP-Projekt + YouTube Data API v3 | ja |
| OAuth Consent (Testing → Production + Verification) | ja für fremde Tenants; Testing ok für DG-MVP |
| Scopes: `youtube.upload` (+ force-ssl nach Doc) | ja |
| Quota-Monitoring (Default 100 inserts/Tag) | ja |
| Content ID / Partner API | **nein** |

Docs: [videos.insert](https://developers.google.com/youtube/v3/docs/videos/insert) · [Resumable](https://developers.google.com/youtube/v3/guides/using_resumable_upload_protocol) · [OAuth Server](https://developers.google.com/youtube/v3/guides/auth/server-side-web-apps) · [Quota](https://developers.google.com/youtube/v3/determine_quota_cost)

### CRM-Deltas

| Delta | Detail |
|-------|--------|
| `oauth_connections.platform=youtube` | channel_id, refresh offline |
| `publish_posts` | video_id, privacyStatus, publishAt? |
| Job-Payload | title, description, tags?, categoryId?, privacy, thumbnail_path? |

### API-Schritte

1. Connect: 3-legged OAuth, Offline-Access, Refresh speichern (**W**)
2. Job: Resumable `videos.insert` Chunks + Retry (**W→V**; große Dateien **V**)
3. Optional: `thumbnails.set`
4. Post-Mgmt: `videos.update` / `videos.delete`
5. Schedule: `privacyStatus=private` + `status.publishAt` (nur vor Erstveröffentlichung)

### Acceptance

- [x] DG-Kanal verbinden → Token refresh ohne Re-Login — Offline-OAuth + `YouTubeTokenService` (manuell: GCP Testing + Connect)
- [x] Video aus CRM → öffentlich/unlisted/private auf YT — Resumable Upload + Privacy-Feld (manuell gegen Live-API prüfen)
- [x] Titel/Beschreibung nachträglich editierbar; Löschen ok — `videos.update` / `videos.delete` über CRM-UI
- [x] Optional: geplanter PublishAt erscheint zum Termin — `publishAt` im Payload (YouTube nur bei `private`)
- [x] Fehler → Job `failed` + lesbare Meldung; kein Token-Leak in Logs — Selftest + `last_error`

### Do NOT yet

- [x] LinkedIn / IG / TikTok — bewusst Phase 2–4
- [x] YouTube Analytics API (später Scope) — bewusst ausgelassen
- [x] Comment-Moderation — bewusst ausgelassen
- [x] Content ID — bewusst ausgelassen

**Host:** OAuth+kleine Uploads **W**; resumable >~100 MB / parallel **V** empfohlen.

**Code (Phase 1):** Settings-Tab *YouTube Publish*, OAuth `/oauth/youtube/*`, Modul Videos, `YouTubeResumableUploadService`, Cron `video-publish`.

---

## Phase 2 — LinkedIn

**Ziel:** Company Page (IQ Strom) Video-Post; optional Member; CRM-seitiges Scheduling.

### Prerequisites

| Item | Pflicht |
|------|---------|
| LinkedIn App als **juristische Person** | ja |
| Community Management: Dev → Integration → **Standard Tier** (Screencast) | ja für Produktion |
| Page-Admin-Rolle am Member | ja für Org-Posts |
| Scopes `w_organization_social` (+ `r_…`); optional `w_member_social` | ja |
| Programmatic Refresh (~1 Jahr) | Partner-Freischaltung; sonst Re-Auth vor ~60d Access-Ablauf |

Docs: [Videos API](https://learn.microsoft.com/en-us/linkedin/marketing/community-management/shares/videos-api?view=li-lms-2026-10) · [Posts API](https://learn.microsoft.com/en-us/linkedin/marketing/community-management/shares/posts-api?view=li-lms-2026-10) · [App Review](https://learn.microsoft.com/en-us/linkedin/marketing/community-management-app-review?view=li-lms-2026-06) · [Refresh](https://learn.microsoft.com/en-us/linkedin/shared/authentication/programmatic-refresh-tokens)

### CRM-Deltas

| Delta | Detail |
|-------|--------|
| Connection | person URN **und/oder** organization URN; Rolle prüfen |
| Asset-Validierung | MP4, 75 KB–500 MB Feed, 3 s–30 Min |
| Job | initializeUpload → chunks (~4 MB) → finalize → `POST /rest/posts` |
| Headers | `Linkedin-Version`, `X-Restli-Protocol-Version: 2.0.0` versionieren |
| Schedule | `scheduled_at` im Job; **kein** natives LI-Schedule |

### API-Schritte

1. OAuth Member → Org wählen (**W**)
2. Videos API Multipart Upload (**W→V**)
3. Posts API `lifecycleState: PUBLISHED`
4. Edit: Posts PARTIAL_UPDATE commentary; Delete: `DELETE /rest/posts/{urn}`
5. Comments/Insights: **nach** stabilem Publish nachziehen

### Phase 2-1 (App + CRM-Scaffold) — Stand

- [x] Checkliste juristische Person / Community Management — [`linkedin-video-phase-2-1.md`](linkedin-video-phase-2-1.md)
- [x] CRM-Tab **LinkedIn Publish** (Credentials, Org-ID, Review-Tier, OAuth Connect)
- [x] `dg_oauth_connections.platform=linkedin` + Crypto (wie YouTube)
- [ ] Manuell: App unter IQ Strom GmbH anlegen, Dev-Tier, Redirect, Connect (Dietrich)
- [ ] Standard-Tier / Screencast — später

### Acceptance

- [ ] IQ-Strom-Page verbunden; Post erscheint im Feed — Upload erst 2-2
- [ ] Caption edit + Delete aus CRM — erst 2-2
- [ ] Geplanter Job feuert per Cron zum Termin — erst 2-2
- [~] Dev-Tier-Limits dokumentiert; Standard-Tier-Antrag gestartet/bestanden — Limits in Checkliste; Antrag manuell

### Do NOT yet

- [x] Instagram / TikTok — bewusst Phase 3–4
- [x] Full Comments/Analytics UI (Stub ok) — bewusst später
- [x] Eigenes Transcode außer MP4-Check — bewusst später

**Host:** wie YT — Chunk-Upload **W→V**.

**Code (Phase 2-1):** `LinkedInPublishSettings` / OAuth / Tab `linkedin-publish` · Selftest `bin/linkedin-publish-selftest.php`.

---

## Phase 3 — Instagram (Reels)

**Ziel:** Professional Accounts → Reels publish; Multi-Tenant nach Advanced Access.

### Prerequisites

| Item | Pflicht |
|------|---------|
| Meta App + Business Portfolio | ja |
| IG Professional (Business/Creator) | ja — Consumer **nein** |
| Advanced Access + oft Business Verification (fremde Tenants) | ja |
| Perms `instagram_content_publish` / business variant | ja |
| Login-Pfad wählen | FB-Login wenn Delete nötig; sonst IG-Login einfacher |

Docs: [Content Publishing](https://developers.facebook.com/docs/instagram-platform/instagram-graph-api/content-publishing/) · [IG Media](https://developers.facebook.com/docs/instagram-platform/instagram-graph-api/reference/ig-user/media/) · [App Review](https://developers.facebook.com/docs/instagram-platform/app-review/)

### CRM-Deltas

| Delta | Detail |
|-------|--------|
| Connection | ig-user-id, page-token / long-lived token |
| Asset | MP4/MOV Reels-Specs (~300 MB, 3 s–15 Min, 9:16 empfohlen) |
| Job | create media container → poll FINISHED → `media_publish` |
| Öffentliche `video_url` **oder** resumable (rupload) | URL braucht erreichbaren Host (**W** mit Public-URL; groß **V**) |
| Flags | `caption_editable=false` nach Publish |

### API-Schritte

1. Connect Professional Account (**W**)
2. `POST /{ig-user-id}/media` (`REELS`) → Status pollen → `media_publish`
3. Schedule nur CRM-deferred
4. Comments/Insights optional nachziehen; Delete nur FB-Login-Pfad

### Acceptance

- [ ] Reel erscheint auf IG-Account
- [ ] Quota `content_publishing_limit` geprüft vor Job
- [ ] Kein Caption-Edit nach Publish in UI angeboten
- [ ] Advanced Access für ≥1 Test-Tenant ok **oder** klar „nur App-Rollen“ für DG

### Do NOT yet

- [ ] TikTok
- [ ] Stories als Kernfeature
- [ ] Product Tagging (außer explizit gefordert)

**Host:** Container-Polling Cron **W**; große Dateien + stabile Public-URL **V** besser.

---

## Phase 4 — TikTok

**Ziel:** Nur wenn CRM als **öffentliches** Creator-/Marketing-Produkt positioniert; sonst skip für rein internes DG.

### Prerequisites

| Item | Pflicht |
|------|---------|
| TT4D App + Login Kit | ja |
| Scopes `video.publish` (Direct) oder `video.upload` (Inbox) | ja |
| **Client-Audit** für public Direct Post | ja |
| UX Guidelines 1:1 (Privacy manuell, Disclosure, Preview, Creator-Info laden, …) | ja — sonst Audit fail |
| Produkt-Position ≠ „internes Upload-Hilfstool“ | ja |

Docs: [Content Posting](https://developers.tiktok.com/products/content-posting-api) · [Direct Post](https://developers.tiktok.com/doc/content-posting-api-get-started) · [Media Transfer](https://developers.tiktok.com/doc/content-posting-api-media-transfer-guide) · [Guidelines](https://developers.tiktok.com/doc/content-sharing-guidelines) · [OAuth](https://developers.tiktok.com/doc/oauth-user-access-token-management)

### CRM-Deltas

| Delta | Detail |
|-------|--------|
| Connection | Creator; Access 24 h + Refresh 365 d (Rotation speichern!) |
| Creator-Info | `max_video_post_duration_sec` vor jedem Post laden |
| Job | init → FILE_UPLOAD chunks **oder** PULL_FROM_URL (Domain verifiziert) → status fetch/webhook |
| UX-Pflichten | Felder laut Guidelines — Feature-Flag `tt_direct_ok` erst nach Audit |

### API-Schritte

1. OAuth Creator (**W**)
2. Ohne Audit: höchstens Inbox (`video.upload`) = Draft in App — **kein** echter One-Click
3. Nach Audit: Direct Post `video.publish`
4. Post-Edit/Delete über Posting-API **nicht** erwarten

### Acceptance

- [ ] Audit bestanden **oder** bewusster Skip
- [ ] Direct: Video öffentlich ohne Extra-Schritt in TikTok-App
- [ ] Rate 6 req/min / Creator-Tagescaps eingehalten
- [ ] Refresh-Rotation persistiert

### Do NOT yet / Skip-Kriterien

- [ ] Vor Phase 1–3 bauen
- [ ] Als reines DG-internes Utility vermarkten
- [ ] Inbox als „One-Click“ verkaufen
- [ ] Für **nur Dietrich intern:** eher **manuell in TikTok-App** / Phase 4 weglassen

**Host:** Chunk/PULL + Webhooks **V** empfohlen; Token-Refresh Cron **W** ok.

---

## Nicht tun (global)

| Verbot |
|--------|
| Content ID / youtubepartner ohne Vertrag |
| IG/TT vor YT+LI |
| Browser-Scraping / inoffizielle APIs |
| Eine gemeinsame Social-Quota annehmen |
| Fremde Publish-SaaS statt CRM |
| Docker/VPS voraussetzen für DG-MVP-Smoke (Foundation+YT klein auf **W**) |

---

## MVP-Schnitt

### A — Dietrich persönlich (empfohlen zuerst)

| Scope | Inhalt | Host |
|-------|--------|------|
| Foundation | OAuth-Store, Asset, Job+Cron, Publish-Button | **W** |
| Phase 1 | YouTube eigener Kanal: Upload, Edit, Delete; optional publishAt | **W→V** |
| Phase 2 | LinkedIn **Company Page** IQ Strom; CRM-Schedule; Caption edit/delete | **W→V** |
| Review-Parallel | YT OAuth-Verify + Quota; LI Standard-Tier-Screencast | organisatorisch |
| Weglassen | IG, TT, Analytics, Comments-UI, Transcode, Multi-Tenant-Onboarding-UX | — |

### B — Multi-Tenant-Extras (nach A)

| Extra | Wann |
|-------|------|
| OAuth Production / App Review für fremde User (YT, LI, Meta) | vor Tenant-Onboarding |
| Tenant-Feature-Flags + Review-Status | mit erstem Fremd-Tenant |
| Phase 3 Instagram Advanced Access + BV | wenn Tenants IG brauchen |
| Phase 4 TikTok nur als Produkt-CRM + Audit | sonst skip |
| VPS Queue-Worker + Transcode H.264 MP4 | bei großen Dateien / Parallel-Uploads |
| Comments + Insights pro Plattform | nach stabilem Publish |

### Go/No-Go

1. **Foundation + YT-Smoke (DG)** auf Webhosting → Go Phase 2.  
2. **LI Standard Tier** genehmigt → Go Produktion LI.  
3. **VPS** wenn Uploads > Cron-Timeout / viele parallele Jobs.  
4. **IG/TT** nur mit Review/Audit-Plan — nicht „schnell mitbauen“.
