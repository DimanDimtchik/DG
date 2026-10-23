# Kommunikation — E-Mail-Postfächer (eigene Implementierung)

Stand: **2026-10-23**

**Entscheidung:** Keine Fremdsoftware (kein Roundcube, SnappyMail, AfterLogic o. Ä.). Nur eigener CRM-Code auf dem bestehenden IMAP/SMTP-Stack.

**Späterer Menü-Rahmen (Kommunikation):**

1. E-Mail-Postfächer ← dieser Plan  
2. Telefon (Telefonanlage — eigener Plan)  
3. Dokumentenverfasser (PDF/Word/Excel — eigener Plan)

---

## Bereits vorhanden (nutzen, nicht neu erfinden)

| Baustein | Ort |
|----------|-----|
| Mailbox-Stammdaten | `MailboxRepository`, Einstellungen / Provisioning |
| IMAP lesen | `ImapMailboxClient`, `MailFolderCatalog`, `MailImapSync` |
| SMTP senden | `MailService`, `SmtpClient`, Einstellungen → E-Mail |
| Archiv / Log | `MailLogRepository`, `storage/mail/` |
| Inbound-Webhook | `MailInboundApi` (u. a. AB aus Angebotsantwort) |
| Bestehende Post-Seite | `/app?page=post` (Compose/Sync-Ansätze) — in P2/P3 ausbauen oder in neue Kommunikations-Routen überführen |

---

## ToDo — Postfächer (P1–P6)

### P1 — Menü „Kommunikation“

- [x] Sidebar-Sektion **Kommunikation** (analog Website/Buchhaltung)
- [x] Eintrag **E-Mail-Postfächer** → bestehende `/app?page=post` (Label angepasst; Liste/Detail = P2/P3)
- [x] Platzhalter-Einträge **Telefon** und **Dokumentenverfasser** (Hinweisseiten)
- [x] Rechte: Modul `post` bzw. Kontakte + canEdit für Platzhalter; Postfach-Detailrechte weiter über `MailboxRepository`

### P2 — Postfach-Liste

- [x] Seite: Liste der sichtbaren Postfächer (Adresse, Typ privat/shared, Status IMAP/SMTP) — `/app?page=post`
- [x] Klick → Postfach öffnen (`/app?page=post&mailbox=id`)
- [x] Kurzstatus soft (Zugangsdaten + ungelesen + letzte Aktivität aus Mail-Log; kein Live-IMAP-Probe)
- [x] Kein Embed fremder Webmailer; Admin-Link „Postfächer verwalten“

### P3 — Postfach-UI (Lesen)

- [x] Ordnerleiste (IMAP-Ordner, deutsche Labels via `MailFolderLabels`) — bestehend + nach P2 nur mit gewähltem Postfach
- [x] Nachrichtenliste (Betreff, Von/An, Datum, gelesen/ungelesen) + Sync-API
- [x] Nachricht öffnen: HTML sanitized (`MailHtmlSanitizer`), Text, Anhänge download, .eml
- [x] „Weitere Nachrichten laden“ (Offset/Limit in API + UI); IMAP-Timeouts unverändert knapp
- [x] MIME-Parser erweitert (`MailMimeReader::parse`), IMAP-Fetch mit Body HTML/Text

### P4 — Schreiben / Antworten (CRM-Compose)

- [x] Neue Nachricht + Antworten + Weiterleiten (`compose` / `reply` / `forward`, Zitat, Wg:/Re:)
- [x] Versand über vorhandenes SMTP der jeweiligen Mailbox (`PostMailComposer` → `MailService`)
- [x] Kopie in Gesendet (lokales Archiv/`__sent__` wie `MailService`; Redirect nach Gesendet)
- [x] Assist: `ContactChannelLink::preferCrmMail()` → `/app?page=post&compose=1&to=`, `mailto:` als Fallback-Link

### P5 — CRM-Fachverknüpfung

- [x] Absender/Empfänger → Kontakt vorschlagen / verknüpfen (`PostMailLinkService`, UI an Nachricht)
- [x] Belegkette: Mail ↔ Beleg (`setVoucherId` / Link-UI; Liste am Belegformular)
- [x] Suche über Postfach + Kontaktbezug (`MailLogRepository::searchForMailboxes`, `q` / `contact`)
- [x] GoBD: `AuditLog` bei Öffnen, Lesen, Senden, Verknüpfen/Lösen (kein stilles Entkoppeln ohne Spur)

### P6 — Härten & Betrieb

- [x] Rechte-Matrix Shared: Lesen/Senden pro Mitglied (`mailbox_member_read[]` / `mailbox_member_send[]`; Senden ⇒ Lesen). Privat = Inhaber. Keine zeitgebundene Vertretung (nur Shared-Mitglieder).
- [x] Anhänge: Download max. 15 MiB, MIME-Whitelist / sonst `octet-stream`, `nosniff`, Dateiname hart (`MailAttachmentGuard`)
- [x] Fehlertexte: `MailProviderError` (Zugangsdaten, Auth, SSL/TLS, Ordner, Timeout, Connect) in Sync-API + Compose-SMTP
- [x] Smoke-Testliste (unten)
- [x] Deploy: nur Master (`deploy.bat` / `deploy.ps1`) → Server `bash bin/sync-crm-from-master.sh` (kein `--delete` am Account-Root)

#### Rechte kurz

| Postfach | Lesen | Senden |
|----------|-------|--------|
| Privat | nur Inhaber | nur Inhaber |
| Shared | Mitglieder mit `can_read` | Mitglieder mit `can_send` (+ implizit Lesen) |
| Admin | immer | immer |

#### Smoke (Master `dg.ganz-om.de`, danach Sync → `ganz-soft.de` / `kontur-cosmetics.de`)

- [ ] Login + Menü **Kommunikation → E-Mail-Postfächer**
- [ ] Postfach-Liste öffnen → ein Postfach → Ordner + Sync
- [ ] Nachricht öffnen (HTML/Text) + Anhang-Download (.eml / Datei)
- [ ] Shared nur Lesen: Liste ja, „Neue Nachricht“ / Antworten ohne Senderecht nein
- [ ] Shared mit Senden: Compose + Versand → Gesendet
- [ ] Kontakt-Vorschlag / Beleg verknüpfen (P5)
- [ ] Suche `q=` und optional Kontakt-ID
- [ ] Falsches IMAP-Passwort: verständlicher Fehler (+ KAS-Hinweis falls provisioniert)
- [ ] Nach Deploy Master: `sync-crm-from-master.sh` auf Instanzen; Shift+Reload; gleiche Checks stichprobenartig

---

## Explizit nicht

- Roundcube / andere Webmail-Suites im CRM-Code oder als Pflicht-iframe  
- Eigenes SMTP-Relay betreiben (Hoster-SMTP der Mailbox nutzen)  
- Telefonanlage und Dokumentenverfasser in diesem Ticket (nur Menü-Platzhalter in P1)

---

## Reihenfolge

`P1 → P2 → P3 → P4 → P5 → P6`  
P1+P2 können in einem Deploy; P3 ist der größte Block.
