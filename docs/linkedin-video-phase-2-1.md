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

## Manuell (pro Tenant / Instanz)

LinkedIn verlangt eine Developer-App unter einer **juristischen Person** und eine **Company Page**. Welche Firma/Page das ist, entscheidet der jeweilige Tenant — **kein** Firmenname im CRM-Kern hardcoden.

- [ ] LinkedIn Developer Portal: App unter der **juristischen Person des Seitenbetreibers** (Company Page)
- [ ] Business-E-Mail, Legal Name, Adresse, Website, Privacy Policy hinterlegen
- [ ] Produkt **Community Management** anfordern (Development Tier)
- [ ] Redirect-URI: `{publicBaseUrl}/oauth/linkedin/callback` der **jeweiligen** CRM-Instanz (Dev zuerst, dann Produktiv-Instanzen)
- [ ] Member mit **Page-Admin**-Rolle an der Unternehmensseite des Tenants
- [ ] Organization ID / URN notieren (`urn:li:organization:…`) — nur in Instanz-Settings
- [ ] Client ID + Client Secret in CRM → Einstellungen → Kommunikation → **LinkedIn Publish** (Instanz-DB)
- [ ] „Mit LinkedIn verbinden“ (Page-Admin-Konto) — Status *verbunden*
- [ ] Dev-Tier-Limits kennen: ca. **500 Calls/App** und **100/Member** / 24h — Produktion braucht Standard Tier

### Standard Tier (nicht 2-1, aber parallel planen)

- [ ] Screencast: OAuth, Posten, Kommentare anzeigen, Datenfelder (laut [App Review](https://learn.microsoft.com/en-us/linkedin/marketing/community-management-app-review?view=li-lms-2026-06))
- [ ] Ablehnung → **neue App** nötig (kein Re-Apply auf derselben App)

### OAuth-Hinweise

- Access-Token ca. **60 Tage**
- Programmatic Refresh (~1 Jahr) nur nach Partner-Freischaltung — sonst vor Ablauf erneut verbinden
- Scopes (MVP Company Page): `openid` `profile` `w_organization_social` `r_organization_social`  
  plus Feed (Kommentare): `w_organization_social_feed` `r_organization_social_feed`  
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

## Phase 2-2 (Code) — erledigt

1. `POST /rest/videos?action=initializeUpload` → Chunks → Finalize → wait `AVAILABLE`  
2. `POST /rest/posts` (`lifecycleState: PUBLISHED`)  
3. Edit commentary (`PARTIAL_UPDATE`) / Delete  
4. CRM-`scheduled_at` (kein natives LI-Schedule)

Live-Test erst nach manueller App + Connect (Checkliste oben).
