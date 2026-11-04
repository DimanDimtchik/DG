# Instagram Video — Phase 3-1 (Meta App + CRM-Scaffold)

**Stand:** 2026-11-04 · Bezug: [`video-plattform-apis-crm.md`](video-plattform-apis-crm.md) §4 · [`video-publish-implementierung.md`](video-publish-implementierung.md) Phase 3

**Ziel:** Meta-App / Business Verification / Advanced Access vorbereiten und CRM-Credentials + OAuth-Connect ablegen. **Noch kein** Reels-Upload (folgt 3-2).

Keine Kundenfirma und keine Personen im Kern hardcoden — App und Professional-Account gehören zur **jeweiligen** Tenant-Instanz.

---

## Abgrenzung

| Thema | Dieses Paket | Später (3-2+) |
|-------|--------------|---------------|
| Meta Developer App + Business Portfolio | ✅ Checkliste | — |
| Business Verification / Advanced Access | ✅ Status-Felder + Checkliste | Review/Screencast manuell |
| CRM-Tab *Instagram Publish* | ✅ | — |
| OAuth Connect (FB-Login oder IG-Login) | ✅ | Token-Pflege / Page-Wahl-UX |
| Reels container → publish | ❌ | ✅ 3-2 (Code fertig; Live nach Connect) |
| Caption-Edit nach Publish | nie (API) | UI bietet keins |
| Kommentare / Insights | ❌ | ✅ 3-3 (Code; Live nach Connect + Scopes) |

---

## Manuell (pro Tenant / Instanz)

### Meta App & Freigaben

- [ ] [Meta for Developers](https://developers.facebook.com/): App anlegen (Typ passend zu Business)
- [ ] App dem **Business Portfolio** des Seitenbetreibers zuordnen
- [ ] Produkte: **Instagram** / Instagram Graph API bzw. Instagram API with Facebook Login
- [ ] OAuth Redirect: `{publicBaseUrl}/oauth/instagram/callback` (Dev zuerst)
- [ ] Privacy Policy der Instanz hinterlegen
- [ ] **Standard Access** / App-Rollen: eigene Test-User & Test-Pages (Smoke ohne Advanced)
- [ ] Für fremde Tenant-Accounts: **Business Verification** + App Review
- [ ] Permission **`instagram_content_publish`** (bzw. Business-Variante) → Advanced Access beantragen
- [ ] Screencast/Use-Case laut [Instagram App Review](https://developers.facebook.com/docs/instagram-platform/app-review/)

### Account

- [ ] Instagram **Professional** (Business oder Creator) — Consumer geht nicht
- [ ] Bei Facebook-Login-Pfad: IG-Konto mit Facebook **Page** verknüpft; Page-Admin verbindet im CRM
- [ ] Optional Page-ID / IG-User-ID in CRM-Settings pinnen

### CRM (nach App-Daten)

- [ ] Einstellungen → Kommunikation → **Instagram Publish**: App-ID, App-Secret, Login-Pfad, Access-Stufe
- [ ] Speichern → **Mit Instagram verbinden**
- [ ] Status *verbunden* mit IG-User / Page-Hinweis

### Ops Redirect-URIs (Infrastruktur)

| Rolle | URI |
|-------|-----|
| Dev | `https://ganz-om.de/oauth/instagram/callback` |
| weitere Instanzen | `{https://<host>}/oauth/instagram/callback` |

---

## Login-Pfad (CRM-Wahl)

| Pfad | Vorteil | Nachteil |
|------|---------|----------|
| **Facebook Login** (Default) | Page-Token, später Delete/mehr Features | Page + Business Setup nötig |
| **Instagram Login** | einfacher für Creator | Delete oft nicht; anderes OAuth |

Scopes (Facebook-Pfad, CRM):  
`instagram_basic` `instagram_content_publish` `pages_show_list` `pages_read_engagement` `business_management`

Scopes (Instagram-Pfad, CRM):  
`instagram_business_basic` `instagram_business_content_publish`

---

## CRM-Code (3-1)

| Baustein | Ort |
|----------|-----|
| Settings | `InstagramPublishSettings`, Tab `instagram-publish` |
| OAuth | `InstagramOAuthClient`, `InstagramTokenService` |
| Tokens | `dg_oauth_connections.platform=instagram` |
| Routes | `/oauth/instagram/start`, `/oauth/instagram/callback` |
| Selftest | `bin/instagram-publish-selftest.php` |

---

## Phase 3-2 (Code) — erledigt

1. Media-Container `REELS` + Status-Polling + `media_publish`  
2. Facebook-Login: resumable (`rupload.facebook.com`); IG-Login: signierte `/video-publish-public/…`  
3. CRM-`scheduled_at` + Queue  
4. Quota `content_publishing_limit` vor Job (weich)  
5. Delete nur FB-Login-Pfad  

Live-Test erst nach manueller Meta-App + Connect (Checkliste oben).

---

## Phase 3-3 (Code) — erledigt

1. Scopes: `instagram_manage_comments` / `instagram_manage_insights` (FB) bzw. Business-Varianten (IG-Login)  
2. Kommentare listen / antworten / ausblenden / löschen  
3. Media-Insights (plays, reach, likes, comments, shares, saved, total_interactions)  
4. Videos-UI-Abschnitt „Instagram: Insights & Kommentare“  

Nach Scope-Erweiterung: erneut verbinden. Live erst mit freigegebenen Permissions.
