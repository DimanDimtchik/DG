# LinkedIn Video — Phase 2-1 (App + CRM-Scaffold)

**Stand:** 2026-11-04 · Bezug: [`video-plattform-apis-crm.md`](video-plattform-apis-crm.md) §2 · [`video-publish-implementierung.md`](video-publish-implementierung.md) Phase 2

**Ziel dieses Schritts:** Community-Management-App unter **juristischer Person** vorbereiten und CRM-Credentials/OAuth-Connect ablegen. **Noch kein** Video-Upload / Posts-API.

---

## Abgrenzung

| Thema | Dieses Paket | Später (2-2/2-3) |
|-------|--------------|------------------|
| LinkedIn Developer App (Legal Org) | ✅ Checkliste | — |
| Community Management Development Tier | ✅ beantragen | Standard Tier + Screencast |
| CRM-Tab *LinkedIn Publish* | ✅ | — |
| 3-legged OAuth → `dg_oauth_connections` | ✅ | Token-Refresh-Politik |
| Videos API + Posts API | ❌ | ✅ |
| Recruiting *LinkedIn Stellenexport* | getrennt lassen | bleibt Job Posting |

Recruiting nutzt **Client Credentials** / Job Posting. Video braucht **Member-OAuth** + `w_organization_social` (Company Page). **Eigene App** oder klar getrennte Produkte — Credentials nicht mit Stellenexport vermischen.

---

## Manuell (Dietrich / IQ Strom)

- [ ] LinkedIn Developer Portal: App unter **juristischer Person** (empfohlen: **IQ Strom GmbH** für Company Page)
- [ ] Business-E-Mail, Legal Name, Adresse, Website, Privacy Policy hinterlegen
- [ ] Produkt **Community Management** anfordern (Development Tier)
- [ ] Redirect-URI (Dev): `https://ganz-om.de/oauth/linkedin/callback`
- [ ] Später Firmen-/Kunden-Instanz: jeweilige `{publicBaseUrl}/oauth/linkedin/callback` ergänzen
- [ ] Member mit **Page-Admin**-Rolle an der IQ-Strom-Unternehmensseite
- [ ] Organization ID / URN notieren (`urn:li:organization:…`)
- [ ] Client ID + Client Secret in CRM → Einstellungen → Kommunikation → **LinkedIn Publish**
- [ ] „Mit LinkedIn verbinden“ (Page-Admin-Konto) — Status *verbunden*
- [ ] Dev-Tier-Limits kennen: ca. **500 Calls/App** und **100/Member** / 24h — Produktion braucht Standard Tier

### Standard Tier (nicht 2-1, aber parallel planen)

- [ ] Screencast: OAuth, Posten, Kommentare anzeigen, Datenfelder (laut [App Review](https://learn.microsoft.com/en-us/linkedin/marketing/community-management-app-review?view=li-lms-2026-06))
- [ ] Ablehnung → **neue App** nötig (kein Re-Apply auf derselben App)

### OAuth-Hinweise

- Access-Token ca. **60 Tage**
- Programmatic Refresh (~1 Jahr) nur nach Partner-Freischaltung — sonst vor Ablauf erneut verbinden
- Scopes (MVP Company Page): `openid` `profile` `w_organization_social` `r_organization_social`  
  Optional später: `w_member_social` für persönliche Posts

---

## CRM-Code (2-1)

| Baustein | Ort |
|----------|-----|
| Settings | `LinkedInPublishSettings`, Tab `linkedin-publish` |
| OAuth | `LinkedInOAuthClient`, `LinkedInTokenService` |
| Tokens | `dg_oauth_connections.platform=linkedin` (Crypto wie YouTube) |
| Routes | `/oauth/linkedin/start`, `/oauth/linkedin/callback` |
| Selftest | `bin/linkedin-publish-selftest.php` |

---

## Nächster Block (2-2+)

1. `POST /rest/videos?action=initializeUpload` → Chunks → Finalize  
2. `POST /rest/posts` (`lifecycleState: PUBLISHED`)  
3. Edit commentary / Delete  
4. CRM-`scheduled_at` (kein natives LI-Schedule)
