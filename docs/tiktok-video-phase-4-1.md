# TikTok Video — Phase 4-1 (TT4D App + CRM-Scaffold)

**Stand:** 2026-11-04 · Bezug: [`video-plattform-apis-crm.md`](video-plattform-apis-crm.md) §3 · [`video-publish-implementierung.md`](video-publish-implementierung.md) Phase 4

**Ziel:** TikTok-for-Developers-App vorbereiten und CRM-Credentials + OAuth-Connect ablegen. **Noch kein** Video-Upload (folgt 4-2).

Keine Kundenfirma und keine Personen im Kern hardcoden — App und Creator-Konto gehören zur **jeweiligen** Tenant-Instanz.

---

## Abgrenzung

| Thema | Dieses Paket | Später (4-2+) |
|-------|--------------|---------------|
| TT4D App + Login Kit + Redirect | ✅ Checkliste | — |
| Client-Audit / Direct-Flag | ✅ Status-Felder | Audit manuell |
| CRM-Tab *TikTok Publish* | ✅ | — |
| OAuth Connect (Creator) | ✅ | Token-Refresh-Cron optional |
| Inbox Upload (`video.upload`) | ❌ | 4-2 |
| Direct Post (`video.publish`) | ❌ | 4-2 nach Audit |
| Caption-Edit / Delete nach Publish | nie (API) | UI bietet keins |

---

## Manuell (pro Tenant / Instanz)

### TikTok for Developers

- [ ] [TikTok for Developers](https://developers.tiktok.com/): App anlegen
- [ ] Produkte: **Login Kit** + **Content Posting API**
- [ ] OAuth Redirect: `{publicBaseUrl}/oauth/tiktok/callback`
- [ ] Privacy Policy der Instanz hinterlegen
- [ ] Scopes beantragen: `user.info.basic` + `video.upload` (Inbox) bzw. später `video.publish` (Direct)
- [ ] UX laut [Content Sharing Guidelines](https://developers.tiktok.com/doc/content-sharing-guidelines) planen (Privacy manuell, Disclosure, Preview, …)
- [ ] Für öffentliche Direct Posts: **Client-Audit** — ohne Audit höchstens Inbox / eingeschränkte Caps

### Produkt-Hinweis

TikTok lehnt typischerweise „utility tool to help upload contents to accounts you manage“ ab. Phase 4 nur sinnvoll, wenn das CRM klar als **öffentliches** Creator-/Marketing-Produkt positioniert ist — sonst eher Skip / manuell in der TikTok-App.

### CRM (nach App-Daten)

- [ ] Einstellungen → Kommunikation → **TikTok Publish**: Client Key, Client Secret, Modus, Audit-Häkchen
- [ ] Speichern → **Mit TikTok verbinden**
- [ ] Status *verbunden* mit Creator-Name / open_id

### Ops Redirect-URIs (Infrastruktur)

| Rolle | URI |
|-------|-----|
| Dev | `https://ganz-om.de/oauth/tiktok/callback` |
| weitere Instanzen | `{https://<host>}/oauth/tiktok/callback` |

---

## Posting-Modus (CRM)

| Modus | Scope | Ergebnis |
|-------|-------|----------|
| **Inbox** (Default) | `video.upload` | Draft in TikTok-Inbox — Creator beendet in App |
| **Direct** | `video.publish` | Nur wenn Audit-Häkchen gesetzt; sonst fällt CRM auf Inbox zurück |

Access-Token ~24 h, Refresh ~365 d — **Rotation speichern** (CRM tut das beim Refresh).

---

## CRM-Code (4-1)

| Baustein | Ort |
|----------|-----|
| Settings | `TikTokPublishSettings`, Tab `tiktok-publish` |
| OAuth | `TikTokOAuthClient`, `TikTokTokenService` |
| Tokens | `dg_oauth_connections.platform=tiktok` |
| Routes | `/oauth/tiktok/start`, `/oauth/tiktok/callback` |
| Selftest | `bin/tiktok-publish-selftest.php` |

---

## Phase 4-2 (geplant)

1. Creator-Info (`max_video_post_duration_sec`) vor jedem Job  
2. Inbox: init → FILE_UPLOAD / PULL_FROM_URL → Status  
3. Direct nur mit `audit_ok` + UX-Pflichten  
4. CRM-`scheduled_at` (API plant nicht)  
5. Kein Caption-Edit/Delete über Posting-API erwarten  
