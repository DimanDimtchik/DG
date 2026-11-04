# LinkedIn Video — Phase 2-1 (App + CRM-Scaffold)

**Stand:** 2026-11-04 · Bezug: [`video-plattform-apis-crm.md`](video-plattform-apis-crm.md) §2 · [`video-publish-implementierung.md`](video-publish-implementierung.md) Phase 2  
**Live-Gate / Redirects / Smoke / Screencast:** [`linkedin-video-phase-2-live.md`](linkedin-video-phase-2-live.md)

**Code 2-1…2-3:** erledigt. **Manuell (App + Connect + Live-Test):** offen, Credentials folgen.

---

## Abgrenzung

| Thema | Code | Live |
|-------|------|------|
| LinkedIn Developer App (Legal Org) | Checkliste | manuell |
| Community Management Development Tier | — | manuell |
| CRM-Tab *LinkedIn Publish* | ✅ | — |
| 3-legged OAuth → `dg_oauth_connections` | ✅ | Connect manuell |
| Videos API + Posts API (2-2) | ✅ | Smoke manuell |
| Kommentare / Insights (2-3) | ✅ | Smoke manuell |
| Recruiting *LinkedIn Stellenexport* | getrennt | bleibt Job Posting |

Recruiting nutzt **Client Credentials** / Job Posting. Video braucht **Member-OAuth** + Org-Scopes. **Eigene App** oder klar getrennte Produkte — Credentials nicht mit Stellenexport vermischen.

---

## Manuell (pro Tenant / Instanz)

→ ausführlich: [`linkedin-video-phase-2-live.md`](linkedin-video-phase-2-live.md)

Kurz:

- [ ] App unter juristischer Person des Seitenbetreibers + Community Management (Dev Tier)
- [ ] Redirect-URI der Instanz + Privacy Policy
- [ ] Page-Admin + Organization ID
- [ ] Client ID/Secret in CRM → verbinden
- [ ] Smoke (Post / Edit / Insights / Kommentar)
- [ ] Später: Standard Tier + Screencast

### OAuth-Hinweise

- Access-Token ca. **60 Tage**
- Programmatic Refresh (~1 Jahr) nur nach Partner-Freischaltung — sonst vor Ablauf erneut verbinden
- Scopes (MVP Company Page): siehe Settings-UI / Live-Doc

---

## CRM-Code (2-1…2-3)

| Baustein | Ort |
|----------|-----|
| Settings | `LinkedInPublishSettings`, Tab `linkedin-publish` |
| OAuth | `LinkedInOAuthClient`, `LinkedInTokenService` |
| Tokens | `dg_oauth_connections.platform=linkedin` |
| Routes | `/oauth/linkedin/start`, `/oauth/linkedin/callback` |
| Upload/Posts | `LinkedInApiClient`, `LinkedInResumableUploadService`, Queue |
| Engagement | `LinkedInEngagementService` + Videos-UI |
| Selftest | `bin/linkedin-publish-selftest.php` |
